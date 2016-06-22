<?php

namespace Icinga\Module\Trappola\Handler;

use Icinga\Module\Trappola\Trap;

class TrapHistoryHandler extends TrapHandler
{
    public function process()
    {
        // TODO: Not yet, currently all traps are stored
        // $this->getTrap()->store();
    }

    public function wants(Trap $trap)
    {
        return true;
    }
}
