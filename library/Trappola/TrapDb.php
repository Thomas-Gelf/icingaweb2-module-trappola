<?php

namespace Icinga\Module\Trappola;

use Icinga\Data\Db\DbConnection;

class TrapDb extends DbConnection
{
    protected function db()
    {
        return $this->getDbAdapter();
    }

    public function fetchTraps()
    {
        $select = $this->db()->select()->from(
            'trap',
            array('*', 'timestamp' => 'UNIX_TIMESTAMP(timestamp)')
        )->order('timestamp DESC')
->limit(40);
        return $this->db()->fetchAll($select);
    }

    public function fetchTrapVars($id)
    {
        $select = $this->getTrapVarsQuery($id);
        return $this->db()->fetchAll($select);
    }

    public function getTrapVarsQuery($id)
    {
        return $this->db()->select()->from(
            array('v' => 'trap_varbind'),
            array(
                'v.oid',
                'v.type',
                'v.value',
                //'o.short_name',
                //'o.mib_name',
                //'o.description'
            )
        )->joinLeft(
            array('o' => 'trap_oidcache'),
            'o.oid = v.oid',
            array()
        )->where('v.trap_id = ?', $id)->order('v.oid');
    }

    public function fetchTrap($id)
    {
        $select = $this->db()->select()->from('trap', '*')->where('id = ?', $id);
        return $this->db()->fetchRow($select);
    }
}

