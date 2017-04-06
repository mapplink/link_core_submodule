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

use Web\Controller\CRUD\AbstractCRUDController;


class AuditLogAdminController extends AbstractCRUDController
{
    /**
     * Child classes should override to return the Entity class name that this CRUD controller works on.
     * @return string
     */
    protected function getEntityClass(){
        return 'Log\Entity\AuditLog';
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
            'Code'         => array(),
            'Data'         => array(),
            'Timestamp'    => array('sortable' => true),
            'Summary'      => array(),
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
            'summary' => array(
                'operators' => array('contains', 'equals'),
                'label'     => 'Summary',
                'field'     => 'summary',
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
