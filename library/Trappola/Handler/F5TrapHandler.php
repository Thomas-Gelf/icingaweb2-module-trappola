<?php

namespace Icinga\Module\Trappola\Handler;

use Icinga\Module\Trappola\Icinga\IcingaTrapIssue as Issue;
use Icinga\Module\Trappola\Trap;

class F5TrapHandler extends TrapHandler
{
    protected $oid = '.1.3.6.1.4.1.3375.2.4.0.';

    public function mangle(Trap $trap)
    {
        if ($var = $trap->getVarbind('.1.3.6.1.4.1.3375.2.4.1.1')) {
            $trap->message = $trap->message . ' ' . $var->value;
        }
    }
}
