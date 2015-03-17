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
use Email\Entity\EmailTemplate;

class EmailTemplateAdminController extends AbstractCRUDController
{
    /**
     * Child classes should override to return the Entity class name that this CRUD controller works on.
     * @return string
     */
    protected function getEntityClass(){
        return 'Email\Entity\EmailTemplate';
    }

    protected function getListViewConfig()
    {
        return array(
            'Code'         => array('linked' => true, 'sortable' => true), 
            'Title'        => array('linked' => true, 'sortable' => true),
            'HumanName'    => array(),
            'SenderName'   => array(),
            'SenderEmail'  => array(),
        );
    }

    /**
     * Set Filter Config
     */
    protected function getSearchFilterConfig()
    {

        return array(
            'content_type' => array(
                'operators'           => array_combine(EmailTemplate::getAllMimeTypes(), EmailTemplate::getAllMimeTypes()),
                'operatorsKeyAsValue' => true,
                'isOperatorValue'     => true,
                'label'               => 'Content Type',
                'field'               => 'mimeType',
                'valuetype'           => 'Hidden'
            ),

            'section' => array(
                'operators'           => $this->getRepo('Email\Entity\EmailTemplateSection')->getSectionsNameIndexdById(),
                'operatorsKeyAsValue' => true,
                'isOperatorValue'     => true,
                'label'               => 'Section',
                'field'               => 'emailTemplateSection',
                'valuetype'           => 'Hidden'
            ),

            'code' => array(
                'operators' => array('contains', 'equals'),
                'label'     => 'Code',
                'field'     => 'code',
            ),
            'title' => array(
                'operators' => array('contains', 'equals'),
                'label'     => 'Subject',
                'field'     => 'title',
            ),

            'humanname' => array(
                'operators' => array('contains', 'equals'),
                'label'     => 'Human Name',
                'field'     => 'humanName',
            ),
            'sendername' => array(
                'operators' => array('contains', 'equals'),
                'label'     => 'Sender Name',
                'field'     => 'senderName',
            ),
            'senderemail' => array(
                'operators' => array('contains', 'equals'),
                'label'     => 'Sender Email',
                'field'     => 'senderEmail',
            ),
        );
    }

}