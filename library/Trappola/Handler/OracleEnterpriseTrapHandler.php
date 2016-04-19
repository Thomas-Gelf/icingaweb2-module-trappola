<?php

namespace Icinga\Module\Trappola\Handler;

class OracleEnterpriseTrapHandler extends TrapHandler
{
    // Oracle Enterprise Manager trap
    protected $oid = '.1.3.6.1.4.1.111.15.2.0.3';

    public function mangle(Trap $trap)
    {
        // Set host to oraEMNGEventHostName.1 and message to oraEMNGEventMessage.1
        $trap->host_name = $trap->getVarbind('.1.3.6.1.4.1.111.15.3.1.1.24.1')->value;
        $trap->message   = $trap->getVarbind('.1.3.6.1.4.1.111.15.3.1.1.3.1')->value;
    }

    public function isIcingaIssue()
    {
        if ($this->getIcingaHostname() === 'SampleEMTargetHost') {
            return false;
        }
    }

    public function getIcingaHostname()
    {
        $trap = $this->getTrap();

        return $this->stripDomain($trap->getHostname());
        return $trap->getVarByName('oraEMNGEventHostName.1');
    }

    public function getIcingaServicename()
    {
        $service = $this->getTrap()->getVarByName('oraEMNGEventTargetType.1');
        $pattern = '%s [Oracledatenbank]';

        return sprintf($pattern, $this->stripSuffix($service));
    }

    public function getMessage()
    {
        return $trap->getVarByName('oraEMNGEventMessage.1');
    }

    protected function stripSuffix($host)
    {
        return preg_replace('/[_\.].*$/', '', $host);
    }
}