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
use Web\Form\UserForm;
use Magelink\Entity\UserRole;
use Magelink\Entity\User;

class UserAdminController extends AbstractCRUDController
{
    /**
     * Child classes should override to return the Entity class name that this CRUD controller works on.
     * @return string
     */
    protected function getEntityClass(){
        return 'Magelink\Entity\User';
    }

    /**
     * Set list view config
     */
    protected function getListViewConfig()
    {
        return array(
            'Username' => array('linked' => true, 'sortable' => true),
            'Email'    => array('linked' => true, 'sortable' => true),
            'Roles'    => array('collection' => true),
        );
    }

    /**
     * Set Filter Config
     */
    protected function getSearchFilterConfig()
    {
        return array(
            'username' => array(
                'operators' => array('contains', 'equals'),
                'label'     => 'Username',
                'field'     => 'username',
            ),
            'email' => array(
                'operators' => array('contains', 'equals'),
                'label'     => 'Email',
                'field'     => 'email',
            ),
        );
    }

    /**
     * Get form
     */
    protected function getForm($user)
    {
        $currentUser = $this->getCurrentUser();
        
        if (!$currentUser->isAdmin()) {
            $excludeRoleIds = array(UserRole::ROLE_ADMINISTRATOR);
        } else {
            $excludeRoleIds = array();
        }
        $form = new UserForm($this->getEntityManager(), $this->getServiceLocator()->get('zfcuser_user_service'), $excludeRoleIds);

        if ($user->getId()) {
            $form->initFilters(false);
        }

        $form->bind($user);

        return $form;
    }    
}
