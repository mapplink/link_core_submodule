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

    /**
     * @return array $listViewConfig
     */
    protected function getListViewConfig()
    {
        return array(
            'Key'=>array('linked'=>true, 'sortable'=>true),
            'EmailTemplate'=>array('getMethod'=>'getTemplateName'),
        );
    }

}
