<?php

namespace Icinga\Module\Trappola\Controllers;

use Icinga\Module\Trappola\Web\Controller;
use Icinga\Module\Trappola\Trap;

class ShowController extends Controller
{
    public function trapAction()
    {
        $this->view->trap = $trap = Trap::load($this->params->get('id'), $this->db());
        $this->getTabs()->add('trap', array(
            'label' => $trap->getOidName($trap->oid),
            'url'   => $this->getRequest()->getUrl())
        )->activate('trap');
    }
}
