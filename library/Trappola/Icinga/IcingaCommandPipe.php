<?php

namespace Icinga\Module\Trappola\Icinga;

use Icinga\Application\Config;
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
        list($host, $service) = preg_split('/!/', $issue->icinga_object, 2);

        $baseUrl = trim(
            Config::module('trappola')->get('web', 'base_url', 'icingaweb2/'),
            '/'
        );

        $output = sprintf(
            '%s<br /><a href="/%s/trappola/incident/show?host=%s&service=%s"'
            . ' class="icon-right-small">%s</a>',
            $issue->message,
            $baseUrl,
            rawurlencode($host),
            rawurlencode($service),
            'Show details'
        );

        if ($long = $issue->getConsolidatedOutput()) {
            $output .= "\n" . $long;
        }

        return $this->sendCheckResult(
            $host,
            $service,
            $issue->getWorstState(),
            $output
        );
    }

    public function sendCheckResult($host, $service, $status, $message)
    {
        $backend = $this->backend();
        $service = new Service($backend, $host, $service);

        $cmd = new ProcessCheckResultCommand();
        $cmd->setObject($service)
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
            $this->backend = Backend::createBackend(/** TODO: Name **/);
        }
        return $this->backend;
    }
}
