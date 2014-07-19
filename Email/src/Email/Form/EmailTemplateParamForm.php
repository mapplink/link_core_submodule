<?php
/**
 * Magelink\Form
 *
 * @category Magelink
 * @package Magelink\Form
 * @author Seo Yao
 * @author Andreas Gerhards <andreas@lero9.co.nz>
 * @copyright Copyright (c) 2014 LERO9 Ltd.
 * @license Commercial - All Rights Reserved
 */

namespace Email\Form;

use Web\Form\DoctrineZFBaseForm;
use Zend\InputFilter\InputFilter;
use Email\Entity\EmailTemplate;

class EmailTemplateParamForm extends DoctrineZFBaseForm
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
            'name' => 'id',
            'type' => 'Hidden',
        ));

        $this->add(array(
            'name' => 'key',
            'type' => 'Text',
            'options' => array(
                'label' => 'Key',
            ),
        ));

        $this->add(array(
            'type' => 'DoctrineModule\Form\Element\ObjectSelect',
            'name' => 'emailTemplate',
            'options' => array(
                'object_manager' => $this->getEntityManager(),
                'target_class'   => 'Email\Entity\EmailTemplate',
                'label_generator' => function($targetEntity) {
                    return ' ' . $targetEntity->getHumanName();
                },
                'label' => 'Email Template',
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

    protected function initFilters()
    {
    }

}