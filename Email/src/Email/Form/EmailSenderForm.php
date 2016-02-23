<?php
/**
 * @category Email
 * @package Form
 * @author Andreas Gerhards <andreas@lero9.co.nz>
 * @copyright Copyright (c) 2016- LERO9 Ltd.
 * @license Commercial - All Rights Reserved
 */

namespace Email\Form;

use Web\Form\DoctrineZFBaseForm;
use Zend\InputFilter\InputFilter;
use Email\Entity\EmailTemplate;


class EmailSenderForm extends DoctrineZFBaseForm
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
            'name'=>'senderId',
            'type'=>'Hidden'
        ));

        $this->add(array(
            'name'=>'storeId',
            'type'=>'Number',
            'options'=>array('label'=>'Unique store id (use 0 for all stores)')
        ));

        $this->add(array(
            'name'=>'senderName',
            'type'=>'Text',
            'options'=>array('label'=>'Default sender name for this store')
        ));

        $this->add(array(
            'name'=>'senderEmail',
            'type'=>'Text',
            'options'=>array('label'=>'Default sender email for this store')
        ));

        $this->add(array(
            'name'=>'submit',
            'type'=>'Submit',
            'attributes'=>array('value'=>'Save')
        ));
    }

    /**
     * Filter validations
     */
    protected function initFilters()
    {
        $inputFilter = new InputFilter();
        $inputFilter->add(array(
            'name'=>'storeId',
            'required'=>true
        ));
        $inputFilter->add(array(
            'name'=>'senderName',
            'required'=>true
        ));
        $inputFilter->add(array(
            'name'=>'senderEmail',
            'required'=>true,
            'validators'=>array(
                array('name'=>'EmailAddress')
            )
        ));
        $this->setInputFilter($inputFilter);
    }

}
