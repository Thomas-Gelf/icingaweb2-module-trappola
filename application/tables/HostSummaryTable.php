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
            'trap_count'  => 'COUNT(*)'
        );
    }

    protected function renderSourceColumn($row)
    {
        $htm = '';
        if ($row->host_name) {
            $htm .= $this->view()->qlink(
                $row->host_name,
                'trappola/list/traps',
                array(
                    'host_name'   => $row->host_name,
                )
            );

        }

        if ($row->src_address) {
            if ($row->host_name) {
                $htm .= ' (';
            }

            $htm .= $this->view()->qlink(
                $row->src_address,
                'trappola/list/traps',
                array(
                'src_address' => $row->src_address,
                )
            );

            if ($row->host_name) {
                $htm .= ')';
            }
        }

        return $htm;
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
        )//->where('t.timestamp > ?', date('Y-m-d H:i:s', $this->start))
         //->where('t.acknowledged = ?', 'y')
         ->group('t.src_address')
         ->group('t.host_name')
         ->order('COUNT(*) DESC')
         ->order('t.host_name')
         ->order('t.src_address');

        return $query;
    }
}
