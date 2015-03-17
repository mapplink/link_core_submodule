<?php
/**
 * Web\Controller
 *
 * @category    Web
 * @package     Web\Controller
 * @author      Sean Yao <sean@lero9.com>
 * @copyright   Copyright (c) 2014 LERO9 Ltd.
 * @license     Commercial - All Rights Reserved
 */

namespace Web\Controller\CRUD;

use Zend\Mvc\Controller\AbstractActionController;
use Zend\View\Model\ViewModel;
use Web\Controller\CRUD\AbstractCRUDController;
use Web\Form\ConfigForm;
use Magelink\Entity\Config;

class ConfigAdminController extends AbstractCRUDController
{
    /**
     * Child classes should override to return the Entity class name that this CRUD controller works on.
     * @return string
     */
    protected function getEntityClass(){
        return 'Magelink\Entity\Config';
    }

    /**
     * Child classes can override to return whether or not this CRUD controller supports creating entities
     * @return boolean
     */
    protected function getEnableCreate(){
        return false;
    }
    /**
     * Child classes can override to return whether or not this CRUD controller supports deleting entities
     * @return boolean
     */
    protected function getEnableDelete(){
        return false;
    }

    /**
     * Set list view config
     */
    protected function getListViewConfig()
    {
        return array(
            'Module' => array('linked' => true, 'sortable' => true),
            'Name' => array('sortable' => true),
            'Key' => array(),
            'Value' => array(),
            'Default' => array(),
        );
    }

    /**
     * Set Filter Config
     */
    protected function getSearchFilterConfig()
    {
        return array(
            'module' => array(
                'operators' => array('contains', 'equals'),
                'label'     => 'Module',
                'field'     => 'module',
            ),
            'human_name' => array(
                'operators' => array('contains', 'equals'),
                'label'     => 'Name',
                'field'     => 'human_name',
            ),
            'key' => array(
                'operators' => array('contains', 'equals'),
                'label'     => 'Key',
                'field'     => 'key',
            ),
        );
    }

}
