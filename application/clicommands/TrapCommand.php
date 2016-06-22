<?php

namespace Icinga\Module\Trappola\Clicommands;

use Icinga\Cli\Command;
use Icinga\Module\Trappola\Handler\OracleEnterpriseTrapHandler;
use Icinga\Module\Trappola\Redis;
use Icinga\Module\Trappola\Trap;
use Icinga\Module\Trappola\TrapDb;

class TrapCommand extends Command
{
    protected $db;

    protected $redis;

    public function testAction()
    {
        $trap = Trap::load(44085, $this->db());
        echo $trap->getVarbind('.1.3.6.1.4.1.111.15.3.1.1.3.1')->value;
        echo $trap->getVarbindByShortname('oraEMNGEventHostName.1');
        //oraEMNGEventHostName.1
        echo "\n";
    }

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

    protected function storeJsonTrap($json)
    {
        $trap = Trap::fromSerializedJson($json, $this->db());
        $trap->resolveTrapName();
        foreach ($this->trapHandlers() as $handler) {
            if ($handler->wants($trap)) {
                $handler->mangle($trap);
                $handler->process($trap);
            }
        }

        $trap->store();

        return $this;
    }

    public function consumeAction()
    {
        $db = $this->db()->getDbAdapter();
        $hasTransaction = false;
        $cnt = 0;
        $redis = $this->redis();

        while (true) {

            while ($res = $redis->brpop('Trappola::queue', 1)) {
                $cnt++;
                if (! $hasTransaction) {
                    $db->beginTransaction();
                    $hasTransaction = true;
                }
                // res = array(queuename, value)
                $this->storeJsonTrap($res[1]);
                if ($cnt >= 50) {
                    break;
                }
            }
            echo "Got nothing for 1sec\n";

            if ($hasTransaction) {
                if ($cnt > 0) {
                    echo "Committing $cnt events\n";
                    $cnt = 0;
                    $db->commit();
                } else {
                    $db->rollBack();
                }

                $hasTransaction = false;
            }
        }
    }

    protected function trapHandlers()
    {
        $handlers = array(
            new OracleEnterpriseTrapHandler()
        );

        foreach ($handlers as $handler) {
            $handlers->initialize();
        }

        return $handlers;
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

    public function checkAction()
    {
        $host  = $this->params->shift('host');
        $address = $this->params->shift('address');
        $since = $this->params->shift('since');
        $match = $this->params->shift('match');

        $db = $this->db()->getDbAdapter();
        $events = $db->select()->from('trap', 'COUNT(*)');
        if ($host) {
            $events->where('host_name LIKE ?', str_replace('*', '%', $host));
        }
        if ($address) {
            $events->where('src_address LIKE ?', str_replace('*', '%', $address));
        }
        if ($address) {
            $events->where('src_address LIKE ?', str_replace('*', '%', $address));
        }
        if ($match) {
            $events->where('message LIKE ?',  str_replace('*', '%', $match));
        }

        $cnt = $db->fetchOne($events);

        printf("Found %s traps\n", $cnt);
        exit(0);
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
