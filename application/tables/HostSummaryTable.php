<?php

namespace Icinga\Module\Trappola\Tables;

use Icinga\Module\Trappola\Web\Table\QuickTable;

class HostSummaryTable extends QuickTable
{
    protected $searchColumns = array(
        'src_address',
        'host_name'
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
            'source'      => "CASE WHEN t.host_name IS NULL THEN t.src_address ELSE t.host_name || ' (' || t.src_address || ')' END",
            'src_address' => 't.src_address',
            'host_name'   => 't.host_name',
            'trap_count'  => 'COUNT(*)',
        );
    }

    protected function getActionUrl($row)
    {
        if ($row->host_name) {
            return $this->url('trappola/list/traps', array(
                'src_address' => $row->src_address,
                'host_name'   => $row->host_name,
            ));
        } else {
            return $this->url('trappola/list/traps', array(
                'src_address' => $row->src_address,
            ));
        }
    }

    public function getTitles()
    {
        $view = $this->view();
        return array(
            'source'     => $view->translate('Source'),
            'trap_count' => $view->translate('Count'),
        );
    }

    public function getBaseQuery()
    {
        $db = $this->connection()->getConnection();

        $query = $db->select()->from(
            array('t' => 'trap'),
            array()
        )->where('t.timestamp > ?', date('Y-m-d H:i:s', $this->start))
         ->group('t.src_address')
         ->group('t.host_name')
         ->order('COUNT(*) DESC')
         ->order('t.host_name')
         ->order('t.src_address');

        return $query;
    }
}
