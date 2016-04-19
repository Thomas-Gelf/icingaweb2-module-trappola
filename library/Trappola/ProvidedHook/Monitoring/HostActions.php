<?php

namespace Icinga\Module\Trappola\ProvidedHook\Monitoring;

use Icinga\Module\Monitoring\Hook\HostActionsHook;
use Icinga\Module\Monitoring\Object\Host;
use Icinga\Web\Url;

class HostActions extends HostActionsHook
{
    public function getActionsForHost(Host $host)
    {
        return array(
            'Traps' => Url::fromPath(
                'trappola/list/traps',
                // array('src_address' => $host->address)
                array('host_name' => $host->host_name . '.*')
            ),
        );
    }
}

