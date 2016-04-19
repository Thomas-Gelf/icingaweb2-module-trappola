<?php

namespace Icinga\Module\Trappola\Handler;

use Icinga\Module\Trappola\Trap;

abstract class TrapHandler
{
    protected $trap;

    protected $oid;

    public function process(Trap $trap)
    {
        $this->trap = $trap;
        IcingaTrapService::handle($this);
    }

    public function mangle(Trap $trap)
    {
    }

    public function wants(Trap $trap)
    {
        return $trap->oid === $this->oid;
    }
    
    protected function isIcingaIssue()
    {
        return true;
    }

    public function getIcingaObjectname()
    {
        return sprintf(
            '%s!%s',
            $this->getIcingaHostname(),
            $this->getIcingaServicename()
        );
    }

    public function getIcingaHostname()
    {
        return $this->getHostname();
    }

    public function getIcingaServicename()
    {
        return 'SNMP Trap';
    }

    public function getIcingaState()
    {
        return 0;
    }
    
    protected function stripDomain($host)
    {
        return preg_replace('/\..*$/', '', $host);
    }
}