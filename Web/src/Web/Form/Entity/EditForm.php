<?php
/**
 * @package Web\Form
 * @author Matt Johnston
 * @author Andreas Gerhards <andreas@lero9.co.nz>
 * @copyright Copyright (c) 2014 LERO9 Ltd.
 * @license http://opensource.org/licenses/BSD-3-Clause BSD-3-Clause - Please view LICENSE.md for more information
 */

namespace Web\Form\Entity;

use Entity\Entity;
use Entity\Service\EntityService;
use Entity\Service\EntityConfigService;
use Magelink\Exception\MagelinkException;
use Web\Helper\BaseEntityAttributes;
use Zend\Form\Form;
use Zend\ServiceManager\ServiceLocatorAwareInterface;
use Zend\ServiceManager\ServiceLocatorInterface;


class EditForm extends Form implements ServiceLocatorAwareInterface
{
    /** @var string */
    protected $_entityType;
    /** @var ServiceLocatorInterface The service locator */
    protected $_serviceLocator;


    /**
     * Constructor
     * @param string $entityType
     * @param string|NULL $name
     */
    public function __construct($entityType, $name = NULL)
    {
        parent::__construct($name);

        $this->_entityType = $entityType;
        $this->initFields();
    }

    /**
     * Set service locator
     * @param ServiceLocatorInterface $serviceLocator
     */
    public function setServiceLocator(ServiceLocatorInterface $serviceLocator)
    {
        $this->_serviceLocator = $serviceLocator;
    }

    /**
     * Get service locator
     * @return ServiceLocatorInterface
     */
    public function getServiceLocator()
    {
        return $this->_serviceLocator;
    }

    /**
     * Set up fields
     * @return void
     */
    protected function initFields()
    {
        $this->add(array(
            'name' => 'entity_id',
            'type' => 'Hidden',
        ));

        $this->add(array(
            'name' => 'unique_id',
            'type' => 'Text',
            'options' => array(
                'label' => 'Unique ID',
            ),
        ));
        $this->add(array(
            'name' => 'store_id',
            'type' => 'Text',
            'attributes' => array(
              'readonly' => TRUE
            ),
            'options' => array(
                'label' => 'Store ID',
            ),
        ));
        $this->add(array(
            'name' => 'parent_id',
            'type' => 'Text',
            'attributes' => array(
                'readonly' => TRUE
            ),
            'options' => array(
                'label' => 'Parent ID',
            ),
        ));
        $this->add(array(
            'name' => 'updated_at',
            'type' => 'hidden',
            'attributes' => array(
                'readonly' => TRUE
            ),
            'options' => array(
                'enabled'=>false,
                'label' => 'Updated At',
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
     * After binding add more fileds for node data
     * @see parent::bind()
     * @param Entity $object
     * @param mixed $flags
     * @return mixed
     */
    public function bind($object, $flags = \Zend\Form\FormInterface::VALUES_NORMALIZED)
    {
        if(!$object instanceof Entity){
            throw new MagelinkException('Invalid object type!');
        }

        parent::bind($object, $flags);

        /** @var EntityService $entityService */
        $entityService = $this->getServiceLocator()->get('entityService');
        /** @var EntityConfigService $entityConfigService */
        $entityConfigService = $this->getServiceLocator()->get('entityConfigService');

        $attributeHelper = new BaseEntityAttributes($entityConfigService);
        $attributes = $attributeHelper->getEntityAttributes($this->_entityType);

        $data = array();
        if ($object->getId()) {
            $object = $entityService->enhanceEntity(0, $object, array_values($attributes));
            $data = $object->getAllSetData();
            $this->get('entity_id')->setValue($object->getId());
            $this->get('unique_id')->setValue($object->getUniqueId());
            $this->get('store_id')->setValue($object->getStoreId());
            $this->get('parent_id')->setValue($object->getParentId());
        }

        $data['entityTypeStr'] = $this->_entityType;
        foreach ($attributeHelper->getAllAttributesAsFormFields($data) as $fieldData) {
            $this->add($fieldData);
        }
    }

    /**
     * Save associated node data
     * @return boolean
     */
    public function save()
    {
        $existed = (bool) $this->getObject();

        /** @var EntityService $entityService */
        $entityService = $this->getServiceLocator()->get('entityService');
        /** @var EntityConfigService $entityConfigService */
        $entityConfigService = $this->getServiceLocator()->get('entityConfigService');

        if ($existed) {
            // UPDATE @todo
        }else{
            // CREATE @todo
        }

        return true; // Optionally catch exception and return false
    }

}
