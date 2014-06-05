<?php
/**
 * Email\Form
 *
 * @category    Email
 * @package     Email\Form
 * @author      Sean Yao <sean@lero9.com>
 * @copyright   Copyright (c) 2014 LERO9 Ltd.
 * @license     Commercial - All Rights Reserved
 */

namespace Email\Form;

use Web\Form\DoctrineZFBaseForm;
use Zend\InputFilter\InputFilter;
use Email\Entity\EmailTemplate;


class EmailTemplateForm extends DoctrineZFBaseForm
{  

    /**
     * Constructor
     * @param \Doctrine\ORM\EntityManager $entityManager
     * @param string $name
     */
    public function __construct(\Doctrine\ORM\EntityManager $entityManager, $name = null)
    {   
        parent::__construct($entityManager, $name);

        $this->initFields();
        $this->initFilters();
    }

    /**
     * Set up fields
     * @param  array $excludeRoleIds 
     * @return 
     */
    protected function initFields()
    {
        $this->add(array(
            'name' => 'templateId',
            'type' => 'Hidden',
        ));

        $this->add(array(
            'name' => 'humanName',
            'type' => 'Text',
            'options' => array(
                'label' => 'Name',
            ),
        ));

        $this->add(array(
            'name' => 'code',
            'type' => 'Text',
            'options' => array(
                'label' => 'Code',
            ),
        ));

        $this->add(array(
            'type' => 'Select',
            'name' => 'mimeType',
            'options' => array(
                'label' => 'Content Type',
                'value_options' => array_combine(EmailTemplate::getAllMimeTypes(), EmailTemplate::getAllMimeTypes())
            ),
        ));

        //Retrieve roles from a many-to-many relationship
        $this->add(array(
            'type' => 'DoctrineModule\Form\Element\ObjectSelect',
            'name' => 'emailTemplateSection',
            'options' => array(
                'object_manager' => $this->getEntityManager(),
                'target_class'   => 'Email\Entity\EmailTemplateSection',
                'label_generator' => function($targetEntity) {
                    return ' ' . ((string) $targetEntity);
                },
                'label' => 'Section',
            ),
        ));

        $this->add(array(
            'name' => 'title',
            'type' => 'Text',
            'options' => array(
                'label' => 'Subject',
            ),
        ));

        $this->add(array(
            'name' => 'senderName',
            'type' => 'Text',
            'options' => array(
                'label' => 'Sender Name',
            ),
        ));

        $this->add(array(
            'name' => 'senderEmail',
            'type' => 'Text',
            'options' => array(
                'label' => 'Sender Email',
            ),
        ));

        $this->add(array(
            'name' => 'body',
            'type' => 'Textarea',
            'options' => array(
                'label' => 'Template',
            ),
        ));

        $this->add(array(
            'name' => 'submit',
            'type' => 'Submit',
            'attributes' => array(
                'value' => 'Save',
            ),
        ));
    }

    /**
     * Filter validations
     */
    protected function initFilters()
    {
        $inputFilter = new InputFilter();
        $inputFilter->add(array(
            'name'     => 'senderEmail',
            'required' => true,
            'validators' => array(
                array(
                    'name' => 'EmailAddress',
                ),
            ),
        ));

        $this->setInputFilter($inputFilter);
    }

}