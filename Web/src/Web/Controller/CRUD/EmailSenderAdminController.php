<?php
/**
 * @package Web\Controller
 * @author Andreas Gerhards <andreas@lero9.co.nz>
 * @copyright Copyright (c) 2016 LERO9 Ltd.
 * @license http://opensource.org/licenses/BSD-3-Clause BSD-3-Clause - Please view LICENSE.md for more information
 */

namespace Web\Controller\CRUD;

use Web\Controller\CRUD\AbstractCRUDController;
use Email\Entity\EmailTemplate;


class EmailSenderAdminController extends AbstractCRUDController
{
    /**
     * Child classes should override to return the Entity class name that this CRUD controller works on.
     * @return string $entityClass
     */
    protected function getEntityClass()
    {
        return 'Email\Entity\EmailSender';
    }

    /**
     * @return array $listConfigView
     */
    protected function getListViewConfig()
    {
        return array(
            'StoreId'=>array('sortable'=>TRUE),
            'SenderName'=>array('linked'=>TRUE, 'sortable'=>TRUE),
            'SenderEmail'=>array('linked'=>TRUE, 'sortable'=>TRUE)
        );
    }

    /**
     * @return array $searchFilterConfig
     */
    protected function getSearchFilterConfig()
    {
        return array(
            'storeId'=>array(
                'operators'=>array('contains', 'equals'),
                'label'=>'Store Id',
                'field'=>'storeId'
            ),
            'sendername'=>array(
                'operators'=>array('contains', 'equals'),
                'label'=>'Sender Name',
                'field'=>'senderName'
            ),
            'senderemail'=>array(
                'operators'=>array('contains', 'equals'),
                'label'=>'Sender Email',
                'field'=>'senderEmail'
            )
        );
    }

}
