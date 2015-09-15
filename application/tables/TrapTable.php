<?php

namespace Icinga\Module\Trappola\Tables;

use Icinga\Module\Trappola\Web\Table\QuickTable;

class TrapTable extends QuickTable
{
    protected $searchColumns = array(
        'name',
        'message'
    );

    public function getColumns()
    {
        return array(
            'id'          => 't.id',
            'mib_name'    => 't.mib_name',
            'short_name'  => 't.short_name',
            'src_address' => "CASE WHEN t.host_name IS NULL OR t.host_name = '' THEN t.src_address ELSE t.host_name END",
            'name'        => "CASE WHEN t.short_name IS NULL THEN t.oid ELSE t.mib_name || '::' || t.short_name END",
            'message'     => 't.message',
            'timestamp'   => 't.timestamp',
        );
    }

    protected function getActionUrl($row)
    {
        return $this->url('trappola/show/trap', array('id' => $row->id));
    }

    public function getTitles()
    {
        $view = $this->view();
        return array(
            'timestamp'   => $view->translate('Time'),
            'src_address' => $view->translate('Source'),
            // 'name'        => $view->translate('Trap'),
            'message'        => $view->translate('Message'),
        );
    }

    public function getBaseQuery()
    {
        $db = $this->connection()->getConnection();

        $query = $db->select()->from(
            array('t' => 'trap'),
            array()
        )->order('timestamp DESC');

        return $query;
    }
}
