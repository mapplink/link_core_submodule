<?php
/**
 * @package Web\Controller
 * @author Sean Yao
 * @author Andreas Gerhards <andreas@lero9.co.nz>
 * @copyright Copyright (c) 2014 LERO9 Ltd.
 * @license http://opensource.org/licenses/BSD-3-Clause BSD-3-Clause - Please view LICENSE.md for more information
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
