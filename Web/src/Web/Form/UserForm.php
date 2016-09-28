<?php
/**
 * @category Magelink
 * @package Magelink\Form
 * @author Sean Yao
 * @author Andreas Gerhards <andreas@lero9.co.nz>
 * @copyright Copyright (c) 2014 LERO9 Ltd.
 * @license Commercial - All Rights Reserved
 */

namespace Web\Form;

use Web\Form\DoctrineZFBaseForm;
use Zend\Crypt\Password\Bcrypt;
use Zend\InputFilter\InputFilter;
use Magelink\Entity\User;


class UserForm extends DoctrineZFBaseForm
{
    /** @var null|\Zend\ServiceManager\ServiceManagerAwareInterface $this->zfcUserService */
    protected $zfcUserService = NULL;
    /** @var array $this->excludeRoleIds */
    protected $excludeRoleIds = array();

    /**
     * Constructor
     * @param \Doctrine\ORM\EntityManager $entityManager
     * @param \Zend\ServiceManager\ServiceManagerAwareInterface $zfcUserService
     * @param array $excludeRoleIds
     * @param string $name
     */
    public function __construct(\Doctrine\ORM\EntityManager $entityManager, \Zend\ServiceManager\ServiceManagerAwareInterface $zfcUserService, $excludeRoleIds = array(), $name = null)
    {
        parent::__construct($entityManager, $name);

        $this->zfcUserService = $zfcUserService;
        $this->initFields($excludeRoleIds);
        $this->initFilters();
    }

    /**
     * Set up fields
     * @param array $excludeRoleIds
     */
    protected function initFields($excludeRoleIds)
    {
        $this->add(array(
            'name' => 'displayName',
            'type' => 'Text',
            'options' => array(
                'label' => 'Full Name',
            ),
            'attributes'=>array('class'=>'form-control'),
        ));

        $this->add(array(
            'name' => 'username',
            'type' => 'Text',
            'options' => array(
                'label' => 'Username',
            ),
            'attributes'=>array('class'=>'form-control'),
        ));

        $this->add(array(
            'name' => 'email',
            'type' => 'Text',
            'options' => array(
                'label' => 'Email',
            ),
            'attributes'=>array('class'=>'form-control'),
        ));



        $this->add(array(
            'name' => 'userId',
            'type' => 'Hidden',
        ));

        $this->add(array(
            'name' => 'passwordUpdate',
            'type' => 'Password',
            'options' => array(
                'label' => 'Password',
            ),
            'attributes'=>array('class'=>'form-control'),
        ));

        //Retrieve roles from a many-to-many relationship
        $this->add(array(
            'type' => 'DoctrineModule\Form\Element\ObjectMultiCheckbox',
            'name' => 'role',
            'options' => array(
                'object_manager' => $this->getEntityManager(),
                'target_class'   => 'Magelink\Entity\UserRole',
                'label_generator' => function($targetEntity) {
                    return ' ' . ((string) $targetEntity);
                },
                'find_method'    => array(
                    'name'   => 'getAssignableRoles',
                    'params' => array(
                        'excludeIds' => $excludeRoleIds,
                    ),
                ),
                'label' => 'Roles',
            ),
            'attributes'=>array('class'=>'role-checkbox'),
        ));

        $this->add(array(
            'name' => 'submit',
            'type' => 'Submit',
            'attributes' => array(
                'value' => 'Save',
                'class'=>'form-control',
            ),
        ));
    }

    /**
     * Initialize filters
     * @param boolean $passwordRequired
     */
    public function initFilters($passwordRequired = true)
    {
        $inputFilter = new InputFilter();

        $inputFilter->add(array(
            'name'     => 'username',
            'required' => false,
            'validators' => array(
                array(
                    'name' => 'Regex',
                    'options' => array(
                        'pattern' => '/^[a-zA-Z0-9-_\.]{3,25}$/',
                        'messages' => array(
                            \Zend\Validator\Regex::NOT_MATCH => 'Username needs to be 3 characters or more and only alphanumeric and -_. can be used ',
                        ),
                    ),
                ),
            ),
        ));

        $inputFilter->add(array(
            'name'     => 'email',
            'required' => true,
            'validators' => array(
                array(
                    'name' => 'EmailAddress',
                ),
            ),
        ));

        $inputFilter->add(array(
            'name'     => 'passwordUpdate',
            'required' => $passwordRequired,
            'validators' => array(
                array(
                    'name' => 'Regex',
                    'options' => array(
                        'pattern' => User::getPasswordCheckRegex(),
                        'messages' => array(
                            \Zend\Validator\Regex::NOT_MATCH => 'Password needs to be 6 characters or more and only alphanumeric and -_. can be used.',
                        ),
                    ),
                ),
            ),
        ));


        $this->setInputFilter($inputFilter);
    }

    /**
     * Save method
     * @return boolean Whether or not the save succeeded
     * @throws \Doctrine\DBAL\DBALException If an unknown error occurs during save
     */
    public function save()
    {
        if ($newPassword = $this->get('passwordUpdate')->getValue()) {
            $bcrypt = new Bcrypt;
            $bcrypt->setCost($this->zfcUserService->getOptions()->getPasswordCost());
            $this->getObject()->setPassword($bcrypt->create($newPassword));
        }

        try {
            return parent::save();
        } catch (\Doctrine\DBAL\DBALException $e) {
            $message = $e->getMessage();

            if (false !== strpos($message, 'username_UNIQUE')) {
                $this->get('username')->setMessages(array(
                    'invalid' => 'This username has already been taken',
                ));
            } elseif (false !== strpos($message, 'email_UNIQUE')) {
                $this->get('email')->setMessages(array(
                    'invalid' => 'This email address has already been taken',
                ));
            } else {
                throw $e;
            }
        }

        return false;
    }

}
