<?php

namespace Icinga\Module\Trappola\Clicommands;

use Exception;
use Icinga\Application\Logger;
use Icinga\Cli\Command;
use Icinga\Exception\ConfigurationError;
use Icinga\Module\Trappola\Handler\OracleEnterpriseTrapHandler;
use Icinga\Module\Trappola\Handler\OmniPcxTrapHandler;
use Icinga\Module\Trappola\Handler\F5TrapHandler;
use Icinga\Module\Trappola\Redis;
use Icinga\Module\Trappola\Trap;
use Icinga\Module\Trappola\TrapDb;

use Icinga\Module\Trappola\Icinga\IcingaTrapIssue;


class TrapCommand extends Command
{
    protected $db;

    protected $redis;

    protected $handlers;

    public function resendAction()
    {
        $id = $this->params->shift();
        $trap = Trap::load((int) $id, $this->db());
        $this->redis()->lpush('Trappola::queue', $trap->toSerializedJson());
    }

    public function receiveAction()
    {
        $db = $this->db();
        while (false !== ($f = fgets(STDIN, 65535))) {
            $db->getDbAdapter()->beginTransaction();
            $this->storeJsonTrap($f);
            $db->getDbAdapter()->commit();
        }
    }

    public function consumeAction()
    {
        $lastExpirationCleanup = 0;
        while (true) {
            try {
                $lastConnect = time();
                if ($lastExpirationCleanup + 45 < time()) {
                    IcingaTrapIssue::cleanupExpiredIssues($this->db());
                    $lastExpirationCleanup = time();
                }

                $this->consumeFromRedis();
            } catch (Exception $e) {
                Logger::error('(trappola) ' . $e->getMessage());
                $this->db    = null;
                $this->redis = null;
            }

            if ((time() - $lastConnect) < 5) {
                sleep(5);
            }
        }
    }

    public function cleanupAction()
    {
        $configuredStart = $this->Config()->get('db', 'purge_before', '-6 month');
        $start = strtotime($configuredStart);

        if (! $start || $start > (time() - 900)) {

            throw new ConfigurationError(
                '"%s" is not a valid purge time definition',
                $configuredStart
            );
        }

        $res = $this->db()->purgeAcknowledgedEventsBefore($start);

        if ($res > 0) {
            Logger::info('%d acknowledged traps have purged', $res);
        }
    }

    // TODO: This is a prototype
    public function expireAction()
    {
        $db = $this->db();
        $cnt = 0;
        foreach ($db->fetchExpiredIcingaIssues() as $issue) {
            $issue->sendExpiration($commandPipe);
            $cnt++;
        }

        if ($cnt > 0) {
            printf("%d expired Icinga Trap issues have been removed", $cnt);
        }
    }

    /**
     * Run checks against the Trappola Trap database
     *
     * This command can be used as a check plugin
     *
     * USAGE
     *
     * icingacli trappola trap check [options]
     *
     * OPTIONS
     *
     *   --host        Search for a specific host
     *   --address     Alternatively search for a specific host address
     *   --message     Optionally only search for specific message patterns
     */
    public function checkAction()
    {
        $host  = $this->params->shift('host');
        $address = $this->params->shift('address');
        $since = $this->params->shift('since');
        $match = $this->params->shift('match');

        $db = $this->db()->getDbAdapter();
        $events = $db->select()->from('trap', 'COUNT(*)');
        if ($host) {
            if (strpos($host, '*') === false) {
                $events->where('host_name = ?', $host);
            } else {
                $events->where('host_name LIKE ?', str_replace('*', '%', $host));
            }
        }

        if ($address) {
            if (strpos($address, '*') === false) {
                $events->where('src_address = ?', $address);
            } else {
                $events->where('src_address LIKE ?', str_replace('*', '%', $address));
            }
        }

        if ($match) {
            $events->where('message LIKE ?',  str_replace('*', '%', $match));
        }

        $events->where('acknowledged = ?', 'n');

        $cnt = $db->fetchOne($events);

        if ($cnt > 0) {
            printf("ERROR: Found %s unacknowledged traps\n", $cnt);
            exit(2);
        } else {
            printf("OK: Found no unacknowledged traps\n", $cnt);
            exit(0);
        }
    }

    public function mibAction()
    {
        $in = '';
        while (false !== ($f = fgets(STDIN, 65535))) {
            $in .= $f;
        }
        $data = json_decode($f);
        print_r($data);
    }

    protected function consumeFromRedis()
    {
        $db = $this->db()->getDbAdapter();
        $redis = $this->redis();

        while ($res = $redis->brpop('Trappola::queue', 1)) {
            // res = array(queuename, value)

            // TODO: if events come in faster than we are able to process them,
            //       cleanup for expired issues will not take place

            // Eventually: $this->trapCount++;
            try {
                $hasTransaction = false;
                $db->beginTransaction();
                $hasTransaction = true;
                $this->storeJsonTrap($res[1]);
                $db->commit();
            } catch (Exception $e) {
                Logger::error('(trappola) ' . $e->getMessage());

                if ($hasTransaction) {
                    try {
                        $db->rollBack();
                    } catch (Exception $e) {
                        Logger::error(
                            '(trappola) failed to rollback db transaction'
                        );
                    }
                }

                try {
                    $redis->rpush('Trappola::queue', $res[1]);
                } catch (Exception $e) {
                    Logger::error(
                        '(trappola) failed to re-push issue to Redis: '
                        . $e->getMessage()
                    );
                }

                $this->db    = null;
                $this->redis = null;

                return;
            }
        }
    }

    protected function storeJsonTrap($json)
    {
        $trap = Trap::fromSerializedJson($json, $this->db());
        $trap->resolveTrapName();

        foreach ($this->trapHandlers() as $handler) {
            if ($handler->wants($trap)) {
                $handler->mangle($trap);
                $handler->processNewTrap($trap);
            }
        }

        $trap->store();

        return $this;
    }

    protected function trapHandlers()
    {
        if ($this->handlers === null) {
            $this->refreshHandlers();
        }

        return $this->handlers;
    }

    protected function refreshHandlers()
    {
        $handlers = array(
            new OracleEnterpriseTrapHandler(),
            new OmniPcxTrapHandler(),
            new F5TrapHandler()
        );

        $first = true;
        foreach ($handlers as $handler) {
            if ($first) {
                // TODO: $handler::refreshIcingaLookup();
                $first = false;
            }

            $handler->setDb($this->db());
            $handler->initialize();
        }

        $this->handlers = $handlers;
    }

    protected function db()
    {
        if ($this->db === null) {
            $this->app->setupZendAutoloader();
            $resourceName = $this->Config()->get('db', 'resource');
            $this->db = TrapDb::fromResourceName($resourceName);
        }

        return $this->db;
    }

    protected function redis()
    {
        if ($this->redis === null) {
            $this->redis = Redis::instance();
        }

        return $this->redis;
    }
}
