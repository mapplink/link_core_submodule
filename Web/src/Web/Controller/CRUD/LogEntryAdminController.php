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

use Web\Controller\CRUD\AbstractCRUDController;


class LogEntryAdminController extends AbstractCRUDController
{
    /**
     * Child classes should override to return the Entity class name that this CRUD controller works on.
     * @return string
     */
    protected function getEntityClass(){
        return 'Log\Entity\LogEntry';
    }

    /**
     * Child classes can override to return whether or not this CRUD controller supports creating entities
     * @return boolean
     */
    protected function getEnableCreate(){
        return false;
    }
    /**
     * Child classes can override to return whether or not this CRUD controller supports editing entities
     * @return boolean
     */
    protected function getEnableEdit(){
        return false;
    }
    /**
     * Child classes can override to return whether or not this CRUD controller supports deleting entities
     * @return boolean
     */
    protected function getEnableDelete(){
        return false;
    }

    protected function getListViewConfig()
    {
        return array(
            'Id'           => array(),
            'Timestamp'    => array('sortable' => true),      
            'Level'        => array(),
            'Code'         => array(),
            'Module'       => array(),
            'Class'        => array(),
            'Data'         => array(),
        );
    }

    /**
     * Set Filter Config
     */
    protected function getSearchFilterConfig()
    {
        return array(
            'code' => array(
                'operators' => array('contains', 'equals'),
                'label'     => 'Code',
                'field'     => 'code',
            ),
            'data' => array(
                'operators' => array('contains', 'equals'),
                'label'     => 'Data',
                'field'     => 'data',
            ),
            'timestampa' => array(
                'operators' => array('=', '>', '<'),
                'label'     => 'Timestamp A',
                'field'     => 'timestamp',
                'valuetype' => 'Datetime',
            ),
            'timestampb' => array(
                'operators' => array('=', '>', '<'),
                'label'     => 'Timestamp B',
                'field'     => 'timestamp',
                'valuetype' => 'Datetime',
            ),
        );  
    }

}
