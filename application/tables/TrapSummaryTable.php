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
            'trap_name'    => "c.mib_name || '::' || c.short_name",
            'oid'          => 't.oid',
            'trapcount'    => 'COUNT(*)',
            'unacknowledged' => "SUM(CASE WHEN t.acknowledged = 'n' THEN 1 ELSE 0 END)"
        );
    }

    protected function renderTrapcountColumn($row)
    {
        if  ((int) $row->trapcount === 0) {
            return '-';
        }

        $parts = array();
        $trapState = '<span class="%s">%d</span>';
        $ok = $row->trapcount - $row->unacknowledged;
        if ($ok > 0) {
            $parts[] = sprintf($trapState, 'ok', $ok);
        }

        if ($row->unacknowledged > 0) {
            $parts[] = sprintf($trapState, 'critical', $row->unacknowledged);
        }

        return implode(' / ', $parts);
    }

    protected function getActionUrl($row)
    {
        return $this->url('trappola/list/traps', array('oid' => $row->oid));
    }

    public function getTitles()
    {
        $view = $this->view();
        return array(
            'trap_name'      => $view->translate('Trap'),
            'trapcount'     => $view->translate('Count'),
        );
    }

    public function count()
    {
        $db = $this->connection()->getConnection();
        $sub = clone($this->getBaseQuery());
        $sub->columns($this->getColumns());
        $this->applyFiltersToQuery($sub);
        $query = $db->select()->from(
            array('sub' => $sub),
            'COUNT(*)'
        );

        return $db->fetchOne($query);
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
        )//->where('t.timestamp > ?', date('Y-m-d H:i:s', $this->start))
         ->group('t.oid_checksum')
         ->order('c.mib_name ASC')
         ->order('c.short_name ASC');

        return $query;
    }

    protected function renderRow($row)
    {
        $htm = "  <tr" . $this->getRowClassesString($row) . ">\n";
        $firstCol = true;

        foreach ($this->getTitles() as $key => $title) {

            // Support missing columns
            if (property_exists($row, $key)) {
                $val = $row->$key;
            } else {
                $htm .= "    <td>-</td>\n";
                continue;
            }

            $func = 'render' . ucfirst($key) . 'Column';
            if (method_exists($this, $func)) {
                $value = $this->$func($row);
                $firstCol = false;
            } elseif ($firstCol) {
                if ($url = $this->getActionUrl($row)) {
                    $value = $this->view()->qlink($val, $this->getActionUrl($row));
                } else {
                    $value  = $val;
                }

                $firstCol = false;
            } else {
                $value = $this->view()->escape($val);
            }

            $htm .= '    <td>' . $value . "</td>\n";
        }

        if ($this->hasAdditionalActions()) {
            $htm .= '    <td class="actions">' . $this->renderAdditionalActions($row) . "</td>\n";
        }

        return $htm . "  </tr>\n";
    }
}
