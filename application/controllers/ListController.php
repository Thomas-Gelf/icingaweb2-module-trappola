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
        $this->prepareTabs()->activate('traps');
        $this->setAutorefreshInterval(10);
        $this->view->traps = $this->db()->fetchTraps();
    }

    public function trapsummaryAction()
    {
        $start = $this->getRequest()->getUrl()->getParams()->shift('start', '-1day');
        $table = $this->loadTable('trapSummary')
            ->setConnection($this->db())
            ->setStart($start);

        $this->view->table = $this->applyPaginationLimits($table);
        $this->view->filterEditor = $table->getFilterEditor($this->getRequest());
        $this->prepareTabs()->activate('trapsummary');

        $this->setAutorefreshInterval(10);
        $this->view->traps = $this->db()->fetchTraps();

        $this->render('traps');
    }

    public function hostsummaryAction()
    {
        $start = $this->getRequest()->getUrl()->getParams()->shift('start', '-1day');
        $table = $this->loadTable('hostSummary')
            ->setConnection($this->db())
            ->setStart($start);

        $this->view->table = $this->applyPaginationLimits($table);
        $this->view->filterEditor = $table->getFilterEditor($this->getRequest());
        $this->prepareTabs()->activate('hostsummary');

        $this->setAutorefreshInterval(10);
        $this->view->traps = $this->db()->fetchTraps();

        $this->render('traps');
    }

    protected function prepareTabs()
    {
        return $this->getTabs()->add('traps', array(
            'label' => $this->translate('Traps'),
            'url'   => 'trappola/list/traps')
        )->add('trapsummary', array(
            'label' => $this->translate('Summary'),
            'url'   => 'trappola/list/trapsummary')
        )->add('hostsummary', array(
            'label' => $this->translate('Hosts'),
            'url'   => 'trappola/list/hostsummary')
        );
    }
}
