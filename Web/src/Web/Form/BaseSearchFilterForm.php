<?php
/**
 * @package Web\Form
 * @author Sean Yao
 * @author Andreas Gerhards <andreas@lero9.co.nz>
 * @copyright Copyright (c) 2014 LERO9 Ltd.
 * @license http://opensource.org/licenses/BSD-3-Clause BSD-3-Clause - Please view LICENSE.md for more information
 */

namespace Web\Form;

use Zend\Form\Form;


class BaseSearchFilterForm extends Form
{

    /** @var array $this->fieldGroup  Grouping the form fields for the entity fields */
    protected $fieldGroup = array();


    /**
     * Constructor
     * @param array $config
     * @param string $name
     */
    public function __construct($config, $name = null)
    {
        parent::__construct($name);

        $this->initFields($config);
    }

    /**
     * Get groups for fields
     * @return array
     */
    public function getGroup()
    {
        return $this->fieldGroup;
    }

    /**
     * Set up fields
     * @return
     */
    protected function initFields($config)
    {
        //For each entity field, there will be a label, an operator and an value and a entity filed name
        foreach ($config as $name => $fieldConfig) {

            $this->fieldGroup[] = $name;

            $this->add(array(
                'name' => $name . '[operator]',
                'type' => 'Select',
                'options' => array(
                    'label' => $fieldConfig['label'],
                    'attributes'=>array('class'=>'form-control'),
                    'value_options' =>
                        array('disabled' => 'disabled')
                        +
                        (isset($fieldConfig['operatorsKeyAsValue']) ?
                            $fieldConfig['operators'] :
                            array_combine($fieldConfig['operators'], $fieldConfig['operators']))

                ),
            ));

            $this->add(array(
                'name' => $name . '[value]',
                'type' => (isset($fieldConfig['valuetype']) ? $fieldConfig['valuetype'] : 'Text'),
                'attributes'=>array('class'=>'form-control'),
            ));

            $this->add(array(
                'name' => $name . '[field]',
                'type' => 'Hidden',
                'attributes' => array(
                    'value' => $fieldConfig['field'],
                    'class'=>'form-control',
                ),
            ));
        }

    }

    /**
     * Update the form values
     * @param  \Zend\Stdlib\Parameters $data
     */
    public function updateFormValues($data)
    {
        if ($data) {
            foreach ($this->fieldGroup as $group) {
                if ($dataArray = $data->get($group)) {
                    $this->get($group . '[value]')->setValue($dataArray['value']);
                    $this->get($group . '[operator]')->setValue($dataArray['operator']);
                }
            }
        }

    }

}
