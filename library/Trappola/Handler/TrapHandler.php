<?php

namespace Icinga\Module\Trappola\Handler;

use Icinga\Module\Monitoring\Backend\MonitoringBackend;
use Icinga\Module\Trappola\Icinga\IcingaTrapIssue;
use Icinga\Module\Trappola\Trap;
use Icinga\Module\Trappola\TrapDb;

abstract class TrapHandler
{
    protected $trap;

    protected $oid;

    private $db;

    private $monitoring;

    final public function processNewTrap(Trap $trap)
    {
        $this->trap = $trap;
        return $this->process();
    }

    final public function setDb(TrapDb $db)
    {
        $this->db = $db;
        return $this;
    }

    protected function getDb()
    {
        return $this->db;
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
        $len = strlen($this->oid);
        if (substr($this->oid, -1) === '.') {
            return substr($trap->oid, 0, $len) === $this->oid;
        } else {
            return $trap->oid === $this->oid;
        }
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

    public function getHostname()
    {
        return gethostbyaddr($this->trap->src_address);
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

    public function setMonigoringBackend(MonitoringBackend $backend)
    {
        $this->monitoring = $backend;
        return $this;
    }

    public function getMonitoringBackend()
    {
        if ($this->monitoring === null) {
            $this->monitoring = MonitoringBackend::createBackend();
        }

        return $this->backend;
    }

    protected function refreshIcingaLookup()
    {
        $this->icingaHostsByAddress = $this
            ->getMonitoringBackend()
            ->select()
            ->from(
                'hostStatus',
                array(
                    'host_address',
                    'hostname',
                )
            )->getQuery()->fetchPairs();
    }
}
