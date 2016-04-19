<?php

namespace Icinga\Module\Trappola\Tables;

use Icinga\Module\Trappola\Web\Table\QuickTable;

class TrapSummaryTable extends QuickTable
{
    protected $searchColumns = array(
        'trap_name',
        'oid',
    );

    protected $start;

    public function setStart($start)
    {
        $this->start = strtotime($start);
        return $this;
    }

    public function getColumns()
    {
        return array(
            'trap_name'   => "c.mib_name || '::' || c.short_name",
            'oid'         => 't.oid',
            'trap_count'  => 'COUNT(*)',
        );
    }

    protected function getActionUrl($row)
    {
        return $this->url('trappola/list/traps', array('oid' => $row->oid));
    }

    public function getTitles()
    {
        $view = $this->view();
        return array(
            'trap_name'   => $view->translate('Trap'),
            'trap_count' => $view->translate('Count'),
        );
    }

    public function getBaseQuery()
    {
//where t.timestamp > '2016-04-01 00:00:00'group by t.oid
        $db = $this->connection()->getConnection();

        $query = $db->select()->from(
            array('t' => 'trap'),
            array()
        )->join(
            array('c' => 'trap_oidcache'),
            'c.oid = t.oid',
            array()
        )->where('t.timestamp > ?', date('Y-m-d H:i:s', $this->start))
         ->group('t.oid')
         ->order('c.mib_name ASC')
         ->order('c.short_name ASC');

        return $query;
    }
}
