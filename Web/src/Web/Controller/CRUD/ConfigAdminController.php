<?php
/**
 * @category Web
 * @package Web\Controller
 * @author Sean Yao
 * @author Andreas Gerhards <andreas@lero9.co.nz>
 * @copyright Copyright (c) 2014 LERO9 Ltd.
 * @license http://opensource.org/licenses/BSD-3-Clause BSD-3-Clause - Please view LICENSE.md for more information
 */

namespace Web\Controller\CRUD;

use Magelink\Entity\Config;
use Web\Controller\CRUD\AbstractCRUDController;
use Web\Form\ConfigForm;
use Zend\View\Model\ViewModel;


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
