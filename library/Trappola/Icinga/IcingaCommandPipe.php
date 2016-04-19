<?php

namespace Icinga\Module\Trappola\Icinga;

use Icinga\Module\Monitoring\Backend;
use Icinga\Module\Monitoring\Command\Object\ProcessCheckResultCommand;
use Icinga\Module\Monitoring\Command\Transport\CommandTransport;
use Icinga\Module\Monitoring\Object\Service;

class IcingaCommandPipe
{
    protected $backend;

    protected $transport;

    public function sendIssue(IcingaTrapIssue $issue)
    {
        return $this->sendCheckResult(
            $issue->get('icinga_host'),
            $issue->get('icinga_service'),
            $issue->get('icinga_state'),
            $issue->get('message')
        );
    }

    public function sendResult($host, $service, $status, $message)
    {
        $backend = $this->backend();
        $service = new Service($backend, $host, $service);

        $cmd = new ProcessCheckResultCommand();
        $cmd->setObject($host)
            ->setStatus((int) $status)
            ->setOutput($message)
            // ->setPerformanceData($notYet)
            ;
            
        return $this->getTransport()->send($cmd);
    }

    protected function getTransport()
    {
        if ($this->transport === null) {
            $this->transport = new CommandTransport();
        }

        return $this->transport;
    }

    protected function backend()
    {
        if ($this->backend === null) {
            $this->backend = Backend::createBackend($this->params->get('backend'));
        }
        return $this->backend;
    }
}
