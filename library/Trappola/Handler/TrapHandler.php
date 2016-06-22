<?php

namespace Icinga\Module\Trappola\Handler;

use Icinga\Module\Trappola\Trap;

abstract class TrapHandler
{
    protected $trap;

    protected $oid;

    final public function processNewTrap(Trap $trap)
    {
        $this->trap = $trap;
        return $this->process();
    }

    public function initialize()
    {
    }

    public function process()
    {
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

    protected function getIssueIdentifier()
    {
        return sha1($this->getIcingaObjectName());
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

    protected function getTrap()
    {
        return $this->trap;
    }
    
    protected function stripDomain($host)
    {
        return preg_replace('/\..*$/', '', $host);
    }
}
