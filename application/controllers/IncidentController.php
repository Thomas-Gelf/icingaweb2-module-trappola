<?php

namespace Icinga\Module\Trappola\Controllers;

use Icinga\Module\Trappola\Icinga\IcingaTrapIssue;
use Icinga\Module\Trappola\Web\Controller;
use Icinga\Web\Notification;
use Icinga\Web\Url;

class IncidentController extends Controller
{
    private $host;

    private $service;

    public function requireIcingaServiceParams()
    {
        $this->host = $this->view->host = $this->params->getRequired('host');
        $this->service = $this->view->service = $this->params->getRequired('service');
    }

    public function showAction()
    {
        $this->requireIcingaServiceParams();

        $this->getTabs()->add('incident', array(
            'label' => $this->translate('Trap Incidents'),
            'url'   => $this->getRequest()->getUrl(),
        ))->activate('incident');
        $this->setAutorefreshInterval(10);

        $this->view->title = sprintf(
            'Trap Incidents for %s on %s',
            $this->host,
            $this->service
        );

        $this->view->incidents = IcingaTrapIssue::listForIcingaService(
            $this->host,
            $this->service,
            $this->db()
        );
    }

    public function expireAction()
    {
        $issue = IcingaTrapIssue::loadForHexChecksum(
            $this->params->getRequired('checksum'),
            $this->db()
        )->expire($this->Auth()->getUser()->getUsername());

        Notification::success(
            'Issue has been scheduled for expiration'
        );

        $this->redirectNow(
            Url::fromPath(
                'trappola/incident/show',
                array(
                    'host'    => $issue->icinga_host,
                    'service' => $issue->icinga_service,
                )
            )
        );
    }
}
