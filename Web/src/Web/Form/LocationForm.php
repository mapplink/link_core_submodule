<?php
/**
 * Magelink\Form
 *
 * @category    Magelink
 * @package     Magelink\Form
 * @author      Sean Yao <sean@lero9.com>
 * @copyright   Copyright (c) 2014 LERO9 Ltd.
 * @license     Commercial - All Rights Reserved
 */

namespace Web\Form;

use Magelink\Form\DoctrineZFBaseForm;
use Zend\InputFilter\InputFilter;
use Magelink\Entity\Location;


class LocationForm extends DoctrineZFBaseForm
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
     */
    protected function initFields()
    {
        $this->add(array(
            'name' => 'locationId',
            'type' => 'Hidden',
            'attributes'=>array('class'=>'form-control'),
        ));

        $this->add(array(
            'name' => 'code',
            'type' => 'Text',
            'options' => array(
                'label' => 'Code',
            ),
            'attributes'=>array('class'=>'form-control'),
        ));

        $this->add(array(
            'name' => 'note',
            'type' => 'Textarea',
            'options' => array(
                'label' => 'Note',
            ),
            'attributes'=>array('class'=>'form-control'),
        ));

        $this->add(array(
            'name' => 'isActivated',
            'type' => 'Checkbox',
            'options' => array(
                'label' => 'Is Activated',
            ),
            'attributes'=>array('class'=>'form-control'),
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

    protected function initFilters()
    {
    }

    /**
     * Save method
     * @return boolean Whether or not we succeeded
     * @throws \Doctrine\DBAL\DBALException
     */
    public function save()
    {   
        try {
            return parent::save();
        } catch (\Doctrine\DBAL\DBALException $e) {
            $message = $e->getMessage();

            if (false !== strpos($message, 'code_UNIQUE')) {
                $this->get('code')->setMessages(array(
                    'invalid' => 'This code has already been used',
                ));
            } else {
                throw $e;
            }
        }

        return false;
    }
}