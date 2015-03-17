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
use Email\Entity\EmailTemplateParam;

class EmailTemplateParamAdminController extends AbstractCRUDController
{
    /**
     * Child classes should override to return the Entity class name that this CRUD controller works on.
     * @return string
     */
    protected function getEntityClass(){
        return 'Email\Entity\EmailTemplateParam';
    }

    protected function getListViewConfig()
    {
        return array(
            'Key'           => array('linked' => true, 'sortable' => true), 
            'EmailTemplate' => array('getMethod' => 'getTemplateName'),
        );
    }

}