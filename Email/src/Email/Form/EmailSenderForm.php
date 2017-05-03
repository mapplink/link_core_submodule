<?php
/*
 * @package Email\Form
 * @author Sean Yao
 * @author Andreas Gerhards <andreas@lero9.co.nz>
 * @copyright Copyright (c) 2014 LERO9 Ltd.
 * @license http://opensource.org/licenses/BSD-3-Clause BSD-3-Clause - Please view LICENSE.md for more information
 */

namespace Email\Form;

use Web\Form\DoctrineZFBaseForm;
use Zend\InputFilter\InputFilter;


class EmailSenderForm extends DoctrineZFBaseForm
{

    /**
     * @param \Doctrine\ORM\EntityManager $entityManager
     * @param string $name
     */
    public function __construct(\Doctrine\ORM\EntityManager $entityManager, $name = NULL)
    {
        parent::__construct($entityManager, $name);

        $this->initFields();
        $this->initFilters();
    }

    /**
     * Set up form fields
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
            'type'=>'Text',
            'options'=>array('label'=>'Store id (use 0 for all stores, 1 for NZ and 2 for AU)')
        ));
        $this->add(array(
            'name'=>'code',
            'type'=>'Text',
            'options'=>array('label'=>'Shipping Method Code (leave empty for all methods)')
        ));
        $this->add(array(
            'name'=>'senderName',
            'type'=>'Text',
            'options'=>array('label'=>'Default sender name for store & shipping method')
        ));
        $this->add(array(
            'name'=>'senderEmail',
            'type'=>'Text',
            'options'=>array('label'=>'Default sender email for store & shipping method')
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
            'required'=>TRUE,
            'validators'=>array(
//                array('name'=>'Int')
            )
        ));
        $inputFilter->add(array(
            'name'=>'senderName',
            'required'=>TRUE
        ));
        $inputFilter->add(array(
            'name'=>'senderEmail',
            'required'=>TRUE,
            'validators'=>array(
                array('name'=>'EmailAddress')
            )
        ));
        $this->setInputFilter($inputFilter);
    }

}
