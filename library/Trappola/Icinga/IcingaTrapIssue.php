<?php

namespace Icinga\Module\Trappola\Icinga;

use Icinga\Module\Trappola\Data\Db\DbObject;
use Icinga\Module\Trappola\Handler\TrapHandler;
use Icinga\Module\Trappola\Trap;
use Icinga\Module\Trappola\TrapDb;

class IcingaTrapIssue extends DbObject
{
    // host!service
    protected $table = 'icinga_trap_issue';

    protected $keyName = 'checksum';

    protected $relatedIssues;
    
    protected $defaultProperties = array(
        'id'                     => null,
        // TODO: traphandler is not yet part of the checksum, should be:
        // sha1(icinga_object!trap_handler!remote_identifier)
        'checksum'               => null,
        'icinga_object'          => null,
        'icinga_object_checksum' => null,
        'icinga_host'            => null,
        'icinga_service'         => null,
        'first_event'            => null, // timestamp
        'last_event'             => null, // timestamp
        'cnt_events'             => null,
        'icinga_state'           => null,
        'message'                => null,
        // 'trap_handler'        => null,
        // 'remote_identifier'   => null,
        'expire_after'           => null, // expire the problem
    );

    protected static $icingaServiceStates = array(
        'OK',
        'WARNING',
        'CRITICAL',
        'UNKNOWN'
    );

    public static function handleTrap(TrapHandler $handler, Trap $trap)
    {
        if (! $handler->isIcingaIssue()) {
            return;
        }
        
        $db = $trap->getConnection();
        $key = $handler->getIcingaObjectName();
        $identifier = $handler->getIssueIdentifier();
        $checksum = sha1($identifier, true);
        $objectName = $handler->getIcingaObjectname();

        if (self::exists($checksum, $db)) {
            $issue = self::load($checksum, $db);
        } else {
            $issue = static::create(array(
                'checksum'               => $checksum,
                'icinga_object'          => $objectName,
                'icinga_object_checksum' => sha1($objectName, true),
                'icinga_host'            => $handler->getIcingaHostname(),
                'icinga_service'         => $handler->getIcingaServicename(),
                'first_event'            => self::now(),
            ), $db);
        }

        return $issue->processTrapProperties($handler, $trap);
    }

    protected function icingaServiceStateName($state)
    {
        return self::$icingaServiceStates[$state];
    }

    protected function processTrapProperties(TrapHandler $handler, Trap $trap)
    {
        $this->cnt_events   = (int) $this->cnt_events + 1;
        $this->message      = $trap->message;
        $this->expire_after = null;
        $this->last_event   = self::now();
        $this->icinga_state = $handler->getIcingaState();

        return $this->storeAndRefreshIcinga();
    }

    protected function storeAndRefreshIcinga()
    {
        if ($this->hasBeenModified()) {
            $pipe = new IcingaCommandPipe();
            $this->store();
            $pipe->sendIssue($this);
        }

        return $this;
    }

    protected function fetchRelatedIssues()
    {
        if ($this->relatedIssues === null) {
            $db = $this->getConnection()->getDbAdapter();

            $query = $db->select()->from(
                array('i' => $this->table),
                array(
                    'icinga_state',
                    'expire_after',
                    'message',
                )
            )->where('i.icinga_object_checksum = ?', $this->icinga_object_checksum);

            $this->relatedIssues = $db->fetchAll($query);
        }

        return $this->relatedIssues;
    }

    public static function listForIcingaService($host, $service, TrapDb $connection)
    {
        $db = $connection->getDbAdapter();

        $checksum = sha1($host . '!' . $service, 1);

        $query = $db->select()->from(
                array('i' => 'icinga_trap_issue'),
                array(
                    'checksum'         => 'LOWER(HEX(i.checksum))',
                    'icinga_state'     => 'i.icinga_state',
                    'message'          => 'i.message',
                    'first_event'      => 'i.first_event',
                    'last_event'       => 'i.last_event',
                    'cnt_events'       => 'i.cnt_events',
                    'expire_after'     => 'i.expire_after',
                )
            )->where('i.icinga_object_checksum = ?', $checksum);

        return $db->fetchAll($query);
    }

    public static function existsForIcingaService($host, $service, TrapDb $connection)
    {
        $db = $connection->getDbAdapter();

        $checksum = sha1($host . '!' . $service, 1);

        $query = $db->select()->from(
                array('i' => 'icinga_trap_issue'),
                array('cnt' => 'COUNT(*)')
            )->where('i.icinga_object_checksum = ?', $checksum);

        return $db->fetchOne($query) > 0;
    }

    public static function loadNewestForIcingaService($host, $service, TrapDb $connection)
    {
        $db = $connection->getDbAdapter();

        $checksum = sha1($host . '!' . $service, 1);

        $query = $db->select()->from(
                array('i' => 'icinga_trap_issue'),
                array('checksum' => 'i.checksum')
            )->where('i.icinga_object_checksum = ?', $checksum)
            ->order('id DESC');

        return static::load($db->fetchOne($query), $connection);
    }

    public static function loadNewestForIcingaServiceIfAny(
        $host,
        $service,
        TrapDb $connection
    ) {
        $db = $connection->getDbAdapter();

        $checksum = sha1($host . '!' . $service, 1);

        $query = $db->select()->from(
                array('i' => 'icinga_trap_issue'),
                array('checksum' => 'i.checksum')
            )->where('i.icinga_object_checksum = ?', $checksum)
            ->order('id DESC');

        if (false === ($checksum = $db->fetchOne($query))) {
            return null;
        }

        return static::load($checksum, $connection);
    }

    public static function loadForHexChecksum($checksum, TrapDb $db)
    {
        return static::load(pack('H*', $checksum), $db);
    }

    public static function cleanupExpiredIssues(TrapDb $connection)
    {
        $now = date('Y-m-d H:i:s');
        $db = $connection->getDbAdapter();
        $services = $db->fetchAll(
            $db->select()->distinct()->from(
                array('i' => 'icinga_trap_issue'),
                array(
                    'host'    => 'i.icinga_host',
                    'service' => 'i.icinga_service',
                )
            )->where('i.expire_after IS NOT NULL AND i.expire_after < ?', $now)
        );

        if (empty($services)) {
            return;
        }

        $db->delete(
            'icinga_trap_issue',
            $db->quoteInto('expire_after < ?', $now)
        );

        $pipe = new IcingaCommandPipe();

        foreach ($services as $s) {
            if ($issue = static::loadNewestForIcingaServiceIfAny(
                $s->host,
                $s->service,
                $connection
            )) {
                $pipe->sendIssue($issue);
            } else {
                $pipe->sendCheckResult(
                    $s->host,
                    $s->service,
                    0,
                    'Expired last related issue at ' . $now
                );
            }
        }
    }

    public function expire($author)
    {
        $this->message = sprintf(
            'Manually expired by %s. Was: ',
            $author
        ) . $this->message;
 
        $this->expire_after = date('Y-m-d H:i:s', time() + 900);
        return $this->storeAndRefreshIcinga();
    }

    public function getConsolidatedOutput()
    {
        $list = array();
        foreach ($this->fetchRelatedIssues() as $issue) {
            if ($issue->expire_after !== null) {
                continue;
            }

            $list[] = sprintf(
                '[%s] %s',
                strtoupper($this->icingaServiceStateName((int) $issue->icinga_state)),
                $issue->message
            );
        }

        if (empty($list)) {
            return null;
        }

        return implode("\n", $list) . "\n";
    }

    public function getWorstState()
    {
        $state = $this->fakeStateForIssue($this);
        foreach ($this->fetchRelatedIssues() as $issue) {
            $state = max($state, $this->fakeStateForIssue($issue));
        }

        return $this->restoreLoweredUnknown($state);
    }

    protected function fakeStateForIssue($issue)
    {
        if ($issue->expire_after === null) {
            $state = $issue->icinga_state;
        } else {
            $state = 0;
        }

        return $this->fakeLowerUnknown($state);
    }

    protected function raiseState($state)
    {
        if ($this->fakeLowerUnknown($state) > $this->fakeLowerUnknown($this->icinga_state)) {
            $this->icinga_state = (int) $state;
        }

        return $this;
    }

    protected function fakeLowerUnknown($state)
    {
        $state = (int) $state;
        if ($state === 3) {
            return 1.5;
        }

        return $state;
    }

    protected function restoreLoweredUnknown($state)
    {
        if ($state === 1.5) {
            return 3;
        }

        return $state;
    }

    protected static function now()
    {
        return date('Y-m-d H:i:s');
    }
}
