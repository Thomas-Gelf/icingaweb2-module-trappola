<?php

namespace Icinga\Module\Trappola\Controllers;

use Icinga\Module\Trappola\Web\Controller;

class ListController extends Controller
{
    public function trapsAction()
    {
        $table = $this->loadTable('trap')->setConnection($this->db());
        $this->view->table = $this->applyPaginationLimits($table);
        $this->view->filterEditor = $table->getFilterEditor($this->getRequest());
        $this->getTabs()->add('traps', array(
            'label' => $this->translate('Traps'),
            'url'   => 'trappola/list/traps')
        )->activate('traps');

        $this->setAutorefreshInterval(1);
        $this->view->traps = $this->db()->fetchTraps();
    }
}
