<?php
/**
 * @package Node\Form
 * @author Sean Yao
 * @copyright Copyright (c) 2014 LERO9 Ltd.
 * @license http://opensource.org/licenses/BSD-3-Clause BSD-3-Clause - Please view LICENSE.md for more information
 */

namespace Node\Form;

use Web\Form\DoctrineZFBaseForm;
use Zend\InputFilter\InputFilter;
use Node\Entity\Node;


class NodeForm extends DoctrineZFBaseForm
{

    protected $config;

    /**
     * Constructor
     * @param \Doctrine\ORM\EntityManager $entityManager
     * @param string $name
     */
    public function __construct(\Doctrine\ORM\EntityManager $entityManager, array $config, $name = null)
    {
        parent::__construct($entityManager, $name);

        $this->config = $config;

        $this->initFields();
    }

    /**
     * Set up fields
     *
     * @return void
     */
    protected function initFields()
    {
        $this->add(array(
            'name' => 'nodeId',
            'type' => 'Hidden',
        ));

        $this->add(array(
            'name' => 'name',
            'type' => 'Text',
            'options' => array(
                'label' => 'Name',
            ),
        ));

        $nodeTypes = array_keys($this->config['node_types']);

        $this->add(array(
            'name' => 'type',
            'type' => 'Select',
            'options' => array(
                'label' => 'Type',
                'value_options' => array_combine($nodeTypes, $nodeTypes),
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
     */
    public function bind($object, $flags = \Zend\Form\FormInterface::VALUES_NORMALIZED)
    {
        parent::bind($object, $flags);

        if ($object->getId()) {

            $this->getObject()->loadSimpleData();

            $nodeDataConfig = $this->config['node_types'][$object->getType()]['config'];

            foreach ($nodeDataConfig as $name => $dataConfig) {
                $this->add(array(
                    'name' => $name,
                    'type' => $dataConfig['type'],
                    'options' => array(
                        'label' => $dataConfig['label'],
                    ),
                    'attributes' => array(
                        'value' => $this->getObject()->getSimpleData($name),
                    )
                ));
            }
        }
    }

    /**
     * Save associated node data
     * @return boolean
     */
    public function save()
    {
        $existed = (bool) $this->getObject()->getId();

        $result = parent::save();

        if ($existed) {
            foreach ($this->config['node_types'][$this->getObject()->getType()]['config'] as $name => $config) {
                if ($field = $this->get($name)) {
                    $this->getObject()->setSimpleData($name, $field->getValue());
                }
            }

            $this->getObject()->saveSimpleData();
        }

        return $result;
    }

}