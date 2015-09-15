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

            if ($trap->oid === '.1.3.6.1.4.1.111.15.2.0.3') {
                $trap->host_name = $trap->getVarbind('.1.3.6.1.4.1.111.15.3.1.1.24.1')->value;
                $trap->message   = $trap->getVarbind('.1.3.6.1.4.1.111.15.3.1.1.3.1')->value;
            }

            $trap->store($db);
            $db->getDbAdapter()->commit();
        }
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
