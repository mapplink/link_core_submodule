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


class LocationAdminController extends AbstractCRUDController
{
    /**
     * Child classes should override to return the Entity class name that this CRUD controller works on.
     * @return string
     */
    protected function getEntityClass(){
        return 'Magelink\Entity\Location';
    }

    /**
     * Set list view config
     */
    protected function getListViewConfig()
    {
        return array(
            'Id'           => array('linked' => true),
            'Code'         => array('linked' => true, 'sortable' => true),      
            'Note'         => array(),
            'IsActivated'  => array('type' => 'boolean'),
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
            'note' => array(
                'operators' => array('contains', 'equals'),
                'label'     => 'Note',
                'field'     => 'note',
            ),
            'isactivated' => array(
                'operators' => array('Yes', 'No'),
                'label'     => 'Is Activated',
                'field'     => 'isActivated',
                'valuetype' => 'Hidden',
            ),
        );
    }
}