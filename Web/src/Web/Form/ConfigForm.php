<?php
/**
 * Magelink\Form
 * @category Magelink
 * @package Magelink\Form
 * @author Andreas Gerhards <andreas@lero9.co.nz>
 * @copyright Copyright (c) 2014 LERO9 Ltd.
 * @license http://opensource.org/licenses/BSD-3-Clause BSD-3-Clause - Please view LICENSE.md for more information
 */

namespace Web\Form;

use Zend\InputFilter\InputFilter;
use Magelink\Entity\Config;


class ConfigForm extends DoctrineZFBaseForm
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
            'name' => 'configId',
            'type' => 'Hidden',
            'attributes'=>array('class'=>'form-control'),
        ));

        $this->add(array(
            'name' => 'module',
            'type' => 'Text',
            'options' => array(
                'label' => 'Module',
            ),
            'attributes' => array(
                'readonly' => TRUE,
                'class'=>'form-control',
            ),
        ));

        $this->add(array(
            'name' => 'humanName',
            'type' => 'Text',
            'options' => array(
                'label' => 'Name',
            ),
            'attributes' => array(
                'readonly' => TRUE,
                'class'=>'form-control',
            ),
        ));

        $this->add(array(
            'name' => 'key',
            'type' => 'Text',
            'options' => array(
                'label' => 'Key',
            ),
            'attributes' => array(
                'readonly' => TRUE,
                'class'=>'form-control',
            ),
        ));

        $this->add(array(
            'name' => 'value',
            'type' => 'Text',
            'options' => array(
                'label' => 'Value',
            ),
            'attributes'=>array('class'=>'form-control'),
        ));

        $this->add(array(
            'name' => 'defaultValue',
            'type' => 'Text',
            'options' => array(
                'label' => 'Default',
                'class'=>'form-control',
            ),
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
     * @param  boolean $passwordRequired
     *
     */
    public function initFilters($passwordRequired = true)
    {
        $inputFilter = new InputFilter();
/*
        $inputFilter->add(array(
            'name'     => 'value',
            'required' => true,
            'validators' => array(
                array(
                    'name' => 'Value',
                ),
            ),
        ));
*/
        $this->setInputFilter($inputFilter);
    }

    /**
     * Save method
     * @return
     */
    public function save()
    {
        try {
            return parent::save();
        } catch (\Doctrine\DBAL\DBALException $e) {
            $message = $e->getMessage();
            throw $e;
        }

        return false;
    }

}
