<?php

namespace Icinga\Module\Trappola\Tables;

use Icinga\Module\Trappola\Web\Table\QuickTable;

class TrapTable extends QuickTable
{
    protected $searchColumns = array(
        'message',
        //'host_name',
    );

    protected $lastDay;

    protected $isUsEnglish;

    protected $columnCount;

    public function getColumns()
    {
        return array(
            'id'           => 't.id',
            'oid'          => 't.oid',
            'mib_name'     => 't.mib_name',
            'short_name'   => 't.short_name',
            'host_name'    => 't.host_name',
            'src_address'  => "CASE WHEN t.host_name IS NULL OR t.host_name = '' THEN t.src_address ELSE t.host_name END",
            'name'         => "CASE WHEN t.short_name IS NULL THEN t.oid ELSE t.mib_name || '::' || t.short_name END",
            'message'      => 't.message',
            'timestamp'    => 'UNIX_TIMESTAMP(t.timestamp)',
            'acknowledged' => 't.acknowledged',
        );
    }

    public function render()
    {
        $data = $this->fetchData();

        $htm = '<table' . $this->createClassAttribute($this->listTableClasses()) . '>' . "\n"
             . $this->renderTitles($this->getTitles());
        foreach ($data as $row) {
            $htm .= $this->renderRow($row);
        }
        return $htm . "</tbody>\n</table>\n";
    }

    protected function renderRow($row)
    {
        $row->time = strftime('%H:%M:%S', $row->timestamp);
        return $this->renderDayIfNew($row) . parent::renderRow($row);
    }

    protected function isUsEnglish()
    {
        if ($this->isUsEnglish === null) {
            $this->isUsEnglish = in_array(setlocale(LC_ALL, 0), array('en_US.UTF-8', 'C'));
        }

        return $this->isUsEnglish;
    }

    protected function renderTitles($row)
    {
        return '';
    }

    protected function renderDayIfNew($row)
    {
        $view = $this->view();

        if ($this->isUsEnglish()) {
            $day = date('l, jS F Y', (int) $row->timestamp);
        } else {
            $day = strftime('%A, %e. %B, %Y', (int) $row->timestamp);
        }

        if ($this->lastDay === $day) {
            return;
        }

        if ($this->lastDay === null) {
            $htm = "<thead>\n  <tr>\n";
        } else {
            $htm = "</tbody>\n<thead>\n  <tr>\n";
        }

        if ($this->columnCount === null) {
            $this->columnCount = count($this->getTitles());
        }

        $htm .= '<th colspan="' . $this->columnCount . '">' . $this->view()->escape($day) . '</th>' . "\n";
        if ($this->lastDay === null) {
            $htm .= "  </tr>\n";
        } else {
            $htm .= "  </tr>\n</thead>\n";
        }

        $this->lastDay = $day;

        return $htm . "<tbody>\n";
    }

    protected function getRowClasses($row)
    {
        if ($row->acknowledged === 'y') {
            return array('acknowledged');
        }
    }

    protected function listTableClasses()
    {
        return array_merge(
            parent::listTableClasses(),
            array('traps')
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
            'time'        => $view->translate('Time'),
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
