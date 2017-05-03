<?php
/**
 * @package Web\Form
 * @author Sean Yao
 * @author Andreas Gerhards <andreas@lero9.co.nz>
 * @copyright Copyright (c) 2014 LERO9 Ltd.
 * @license http://opensource.org/licenses/BSD-3-Clause BSD-3-Clause - Please view LICENSE.md for more information
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
            'name'=>'locationId',
            'type'=>'Hidden',
            'attributes'=>array('class'=>'form-control'),
        ));

        $this->add(array(
            'name'=>'code',
            'type'=>'Text',
            'options'=>array(
                'label'=>'Code',
            ),
            'attributes'=>array('class'=>'form-control'),
        ));

        $this->add(array(
            'name'=>'note',
            'type'=>'Textarea',
            'options'=>array(
                'label'=>'Note',
            ),
            'attributes'=>array('class'=>'form-control'),
        ));

        $this->add(array(
            'name'=>'isActivated',
            'type'=>'Checkbox',
            'options'=>array(
                'label'=>'Is Activated',
            ),
            'attributes'=>array('class'=>'form-control'),
        ));

        $this->add(array(
            'name'=>'submit',
            'type'=>'Submit',
            'attributes'=>array(
                'value'=>'Save',
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
        }catch (\Doctrine\DBAL\DBALException $exception) {
            $message = $exception->getMessage();

            if (strpos($message, 'code_UNIQUE') !== false) {
                $this->get('code')->setMessages(array(
                    'invalid'=>'This code has already been used',
                ));
            }else{
                throw $exception;
            }
        }

        return false;
    }

}
