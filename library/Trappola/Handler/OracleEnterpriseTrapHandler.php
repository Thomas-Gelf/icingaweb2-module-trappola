<?php

namespace Icinga\Module\Trappola\Handler;

use Icinga\Module\Trappola\Icinga\IcingaTrapIssue as Issue;
use Icinga\Module\Trappola\Trap;

class OracleEnterpriseTrapHandler extends TrapHandler
{
    // Oracle Enterprise Manager trap
    protected $oid = '.1.3.6.1.4.1.111.15.2.0.3';

    protected $stripDomain = true;

    protected $toUpperCase = true;

    /**
     * oraEMNGEventSeverityCode == CLEAR? -> if hasIssue() ->getIssue()->clear();
     */
    protected $severities = array(
        'FATAL'         => 2,
        'CRITICAL'      => 2,
        'WARNING'       => 1,
        'ADVISORY'      => 0,
        'INFORMATION'   => 0, // INFORMATION, INFORMATIONAL, both?
        'INFORMATIONAL' => 0,
        'CLEAR'         => 0
    );

    protected $icingaStates = array('OK', 'WARNING', 'CRITICAL', 'UNKNOWN');

    public function initialize()
    {
        $this->oidLookup = $this->getDb()->fetchOidNamesByMib('ORACLE-ENTERPRISE-MANAGER-4-MIB');
        $this->nameLookup = array_flip($this->oidLookup);
    }

    protected function getVarByName($name)
    {
        $oid = $this->nameLookup[$name];
        $var = $this->getTrap()->getVarbind($oid);

        if ($var === null) {
            return $var;
        } else {
            return $var->value;
        }
    }

    public function process()
    {
        return; // TODO: temporarily disabled
        $state = $this->getIcingaState();
        if ($state > 0) {
            $issue = $this->createIssue(
                $this->getOracleIssueId()
            );
        } else {
            //if (
        }

        // if ($this->trap->
    }

    protected function getOracleIssueId()
    {
        return $this->getIcingaObjectName() . '!' . $this->getSequenceId();
    }

    protected function createIssue($id)
    {
        return Issue::create(array(
            'checksum' => sha1($id, true)
        ));
    }

    protected function getIcingaStateName()
    {
        return $this->icingaStates[$this->getIcingaState()];
    }

    public function getIcingaState()
    {
        return $this->severities[$this->getSeverityCode()];
    }

    public function mangle(Trap $trap)
    {
        $this->trap = $trap;
        $trap->host_name = $this->getHostname();
        $trap->message   = $this->getMessage();
    }

    protected function getMessage()
    {
        // .1.3.6.1.4.1.111.15.3.1.1.3.1
        return $this->getVarByName('oraEMNGEventMessage.1');
    }

    protected function getSequenceId()
    {
        // .1.3.6.1.4.1.111.15.3.1.1.42.1
        return $this->getVarByName('oraEMNGEventSequenceId.1');
    }

    protected function getNotificationType()
    {
        // .1.3.6.1.4.1.111.15.3.1.1.2.1
        return $this->getVarByName('oraEMNGEventNotifType.1');
    }

    protected function getSeverityCode()
    {
        // .1.3.6.1.4.1.111.15.3.1.1.6.1
        return $this->getVarByName('oraEMNGEventSeverityCode.1');
    }

    public function isIcingaIssue()
    {
        if ($this->getHostname() === 'SampleEMTargetHost') {
            return false;
        }

        return parent::isIcingaIssue();
    }

    public function getIcingaHostname()
    {
        $hostname = $this->getHostname();
        if ($this->stripDomain) {
            $hostname = $this->stripDomain($hostname);
        }
        if ($this->toUpperCase) {
            $hostname = strtoupper($hostname);
        }
        return $hostname;
    }

    public function getHostName()
    {
        // Original host, only for events with a valid target:
        if ($this->getVarByName('oraEMNGEventTargetName.1')) {
            // .1.3.6.1.4.1.111.15.3.1.1.24.1
            return $this->getVarByName('oraEMNGEventHostName.1');
        } else {
            // otherwise keep the existing one - if any
            return $this->getTrap()->host_name;
        }
    }

    public function getIcingaServicename()
    {
        $trap = $this->getTrap();
        $targetType = $this->getVarByName('oraEMNGEventTargetType.1');
        // TargetName: LISTENER_host, DBNAME.domain
        $service = $this->getVarByName('oraEMNGEventTargetName.1');
        $pattern = '%s [Oracledatenbank]';

        return sprintf($pattern, $this->stripSuffix($service));
    }

    protected function stripSuffix($host)
    {
        return preg_replace('/[_\.].*$/', '', $host);
    }
}
