<?php

namespace Icinga\Module\Trappola\Controllers;

use Icinga\Module\Trappola\Web\Controller;
use Icinga\Web\Url;

class ListController extends Controller
{
    public function trapsAction()
    {
        $table = $this->loadTable('trap')->setConnection($this->db());
        $this->view->table = $this->applyPaginationLimits($table);
        $url = $this->getRequest()->getUrl();
        $action = $url->shift('action');
        $maxId = $url->shift('maxId');
        $this->view->filterEditor = $table->getFilterEditor($this->getRequest());
        $filter = $this->view->filterEditor->getFilter();
        if ($action === 'ack' && ! $filter->isEmpty()) {
            $this->db()->update(
                'trap',
                array('acknowledged' => 'y'),
                $filter
            );
            $this->redirectNow($url);
        }
        if ($action === 'unack' && ! $filter->isEmpty()) {
            $this->db()->update(
                'trap',
                array('acknowledged' => 'n'),
                $filter
            );
            $this->redirectNow($url);
        }

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

        $this->render('onlytraps');
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

        $this->render('onlytraps');
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
