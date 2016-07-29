<?php

namespace Icinga\Module\Trappola\Web;

use Icinga\Data\Paginatable;
use Icinga\Exception\ConfigurationError;
use Icinga\Module\Trappola\TrapDb;
use Icinga\Module\Trappola\Web\Form\FormLoader;
use Icinga\Module\Trappola\Web\Table\TableLoader;
use Icinga\Web\Controller as ActionController;
use Icinga\Web\Widget;

abstract class Controller extends ActionController
{
    protected $db;

    protected function applyPaginationLimits(Paginatable $paginatable, $limit = 25, $offset = null)
    {
        $limit = $this->params->get('limit', $limit);
        $page = $this->params->get('page', $offset);

        $paginatable->limit($limit, $page > 0 ? ($page - 1) * $limit : 0);

        return $paginatable;
    }

    public function loadForm($name)
    {
        return FormLoader::load($name, $this->Module());
    }

    public function loadTable($name)
    {
        return TableLoader::load($name, $this->Module());
    }

    protected function setConfigTabs()
    {
        $this->view->tabs = Widget::create('tabs')->add('generatedconfig', array(
            'label' => $this->translate('Configs'),
            'url'   => 'director/list/generatedconfig')
        )->add('activitylog', array(
            'label' => $this->translate('Activity Log'),
            'url'   => 'director/list/activitylog')
        )->add('datalist', array(
            'label' => $this->translate('Data lists'),
            'url'   => 'director/list/datalist')
        )->add('datafield', array(
            'label' => $this->translate('Data fields'),
            'url'   => 'director/list/datafield')
        );
        return $this->view->tabs;
    }

    protected function db()
    {
        if ($this->db === null) {
            $resourceName = $this->Config()->get('db', 'resource');
            if ($resourceName) {
                $this->db = TrapDb::fromResourceName($resourceName);
            } else {
                throw new ConfigurationError(
                    'No database resource has been configured for Trappola'
                );

                // TODO: $this->redirectNow('trappola/welcome');
            }
        }

        return $this->db;
    }
}
