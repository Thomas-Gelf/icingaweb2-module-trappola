<?php

namespace Icinga\Module\Trappola\Icinga;

use Icinga\Module\Trappola\Data\Db\DbObject;

class IcingaTrapService extends DbObject
{
    // host!service
    protected $keyName = 'icinga_object';
    
    protected $properties = array(
        'icinga_object'  => null,
        'icinga_host'    => null,
        'icinga_service' => null,
        'first_event'    => null, // timestamp
        'last_event'     => null, // timestamp
        'cnt_events'     => null,
        'icinga_state'   => null,
        'message'        => null,
        'longtext'       => null,
        'acknowledged'   => null,
        'expiration'     => null, // expire the problem
        // severity, eventname, url
    );
    
    public static function handleTrap(Trap $trap)
    {
        if (!$trap->isIcingaIssue()) {
            return;
        }
        
        $key = $trap->getIcingaObjectName();
        if (self::exists($key)) {
            $issue = $self->
    }
    
    public function fromTrap(Trap $trap)
    {
        return self::create(self::propertiesFromTrap($trap));
    }
    
    public static function propertiesFromTrap(Trap $trap)
    {
        return array(
            'icinga_object'  => $trap->getIcingaObjectname(),
            'icinga_host'    => $trap->getIcingaHostname(),
            'icinga_service' => $trap->getIcingaServicename(),
        );
    }
}

