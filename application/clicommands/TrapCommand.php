<?php

namespace Icinga\Module\Trappola\Clicommands;

use Icinga\Cli\Command;
use Icinga\Module\Trappola\Trap;
use Icinga\Module\Trappola\TrapDb;

class TrapCommand extends Command
{
    protected $db;

    public function receiveAction()
    {
        $db = $this->db();
        while (false !== ($f = fgets(STDIN, 65535))) {
            $data = json_decode($f);
            $db->getDbAdapter()->beginTransaction();
            $trap = Trap::create((array) $data);

            // Oracle Enterprise Manager trap
            // Set host to oraEMNGEventHostName.1 and message to oraEMNGEventMessage.1
            if ($trap->oid === '.1.3.6.1.4.1.111.15.2.0.3') {
                $trap->host_name = $trap->getVarbind('.1.3.6.1.4.1.111.15.3.1.1.24.1')->value;
                $trap->message   = $trap->getVarbind('.1.3.6.1.4.1.111.15.3.1.1.3.1')->value;
            }

            $trap->store($db);
            $db->getDbAdapter()->commit();
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
}