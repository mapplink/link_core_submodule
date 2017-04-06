<?php
/**
 * Web\Helper
 *
 * @category Web
 * @package Web\Helper
 * @author Andreas Gerhards <andreas@lero9.co.nz>
 * @copyright Copyright (c) 2014 LERO9 Ltd.
 * @license http://opensource.org/licenses/BSD-3-Clause BSD-3-Clause - Please view LICENSE.md for more information
 */

namespace Web\Helper;

use Entity\Helper\AbstractHelper;
use Magelink\Exception\MagelinkException;


class BaseEntityAttributes extends AbstractHelper
{

    public function __construct($entityConfigService)
    {
        $this->_entityConfigService = $entityConfigService;
    }


    public function getEntityAttributes($entityType)
    {
        $attributes = $this->_entityConfigService->getAttributesCode($entityType);
        return $attributes;
    }

    protected function getMultiTypeKeySuffix()
    {
        return '_key';
    }

    protected function getMultiTypeValueSuffix()
    {
        return '_value';
    }

    public function isMultiTypeKey($code)
    {
        $suffix = $this->getMultiTypeKeySuffix();
        return substr($code, -strlen($suffix)) == $suffix;
    }

    public function isMultiTypeValue($code)
    {
        $suffix = $this->getMultiTypeValueSuffix();
        return substr($code, -strlen($suffix)) == $suffix;
    }

    public function getMultiTypeCode($code)
    {
        if ($this->isMultiTypeKey($code)) {
            $suffix = $this->getMultiTypeKeySuffix();
        }else{
            $suffix = $this->getMultiTypeValueSuffix();
        }
        $multiTypeFieldName = substr($code, 0, -strlen($suffix));

        return $multiTypeFieldName;
    }

    public function getCombinedMultiFields(array $entityData)
    {
        $multiFieldsData = array();
        foreach ($entityData as $code=>$value) {
            if ($this->isMultiTypeKey($code) || $this->isMultiTypeValue($code)) {
                $multiTypeCode = $this->getMultiTypeCode($code);

                if (array_key_exists($multiTypeCode, $multiFieldsData)) {
                    $multiTypeFieldValue = $multiFieldsData[$multiTypeCode]['multi'];
                }else{
                    $multiTypeFieldValue = array(''=>NULL);
                }

                if ($this->isMultiTypeKey($code)) {
                    $multiFieldValue = array($value=>current($multiTypeFieldValue));
                }else{
                    $multiFieldValue = array(key($multiTypeFieldValue)=>$value);
                }

                $multiFieldsData[$multiTypeCode] = array(
                    'multi'=>$multiFieldValue,
                    'key'=>$multiTypeCode.$this->getMultiTypeKeySuffix(),
                    'value'=>$multiTypeCode.$this->getMultiTypeValueSuffix()
                );
            }
        }

        return $multiFieldsData;
    }

    public function getAllAttributesAsFormFields(array $entityData)
    {
        $formData = array();
        if (!isset($entityData['entityTypeStr'])) {
            throw new MagelinkException('Entity type information is missing. Please add that to the entityData.');
        }else{
            foreach ($this->getEntityAttributes($entityData['entityTypeStr']) as $attributeId=>$attributeCode) {

                $attribute = $this->_entityConfigService->getAttribute($attributeId);

                $fetchData = array();
                if (isset($attribute['fetch_data'])
                    && !is_array($attribute['fetch_data']) && strlen($attribute['fetch_data'])) {

                    $fetchData = unserialize($attribute['fetch_data']);
                }

                $displayData = array();
                if (isset($attribute['display_data'])
                    && !is_array($attribute['display_data']) && strlen($attribute['display_data'])) {

                    $displayData = unserialize($attribute['display_data']);
                }

                $attributes = array();
                $options = array('label'=>$attribute['name']);
                switch (key($displayData)) {
                    case 'boolean':
                        $type = 'Select';
                        $options['empty_option'] = 'Please choose Yes or No';
                        if (isset($entityData[$attribute['code']]) && $entityData[$attribute['code']]) {
                            $options['value_options'] = array(
                                array('label'=>'Yes', 'value'=>1, 'selected'=>TRUE),
                                array('label'=>'No', 'value'=>0)
                            );
                        }else{
                            $options['value_options'] = array(
                                array('label'=>'Yes', 'value'=>1),
                                array('label'=>'No', 'value'=>0, 'selected'=>TRUE)
                            );
                        }
                        break;
                    case 'enum':
                        $type = 'Select';
                        $options['empty_option'] = 'Please choose an option';
                        foreach (current($displayData) as $code => $label) {
                            $option = array('label' => $label, 'value' => $code);
                            if (isset($entityData[$attribute['code']]) && $code == $entityData[$attribute['code']]) {
                                $option['selected'] = TRUE;
                            }
                            $options['value_options'][] = $option;
                        }
                        break;
                    case 'array':
                    default:
                        if (array_key_exists($attributeCode, $entityData) && is_array($entityData[$attributeCode])) {
                            $type = array(
                                $this->getMultiTypeKeySuffix()=>'Text',
                                $this->getMultiTypeValueSuffix()=>'Text'
                            );
                            $attributes = array(
                                $this->getMultiTypeKeySuffix()=>array('value'=>
                                    array_key_exists($attributeCode, $entityData) ? key($entityData[$attributeCode]) : ''
                                ),
                                $this->getMultiTypeValueSuffix()=>array('value'=>
                                    array_key_exists($attributeCode, $entityData) ? current($entityData[$attributeCode]) : ''
                                )
                            );
                            $options = array(
                                $this->getMultiTypeKeySuffix()=>array('label'=>$attribute['name'].' (multi key)'),
                                $this->getMultiTypeValueSuffix()=>array('label'=>$attribute['name'].' (multi value)')
                            );
                        }else{
                            $type = 'Text';
                            $attributes = array(
                                'value'=>array_key_exists($attributeCode, $entityData) ? $entityData[$attributeCode] : ''
                            );
                            if ($attribute['type'] == 'entity') {
                                $attributes['readonly'] = TRUE;
                            }
                        }
                }

                if (is_array($type)) {
                    foreach ($type as $suffix=>$fieldType) {
                        $fieldData = array(
                            'name'=>$attributeCode.$suffix,
                            'type'=>$fieldType,
                            'attributes'=>$attributes[$suffix],
                            'options'=>$options[$suffix]
                        );
                        $formData[] = $fieldData;
                    }
                }else{
                    $fieldData = array(
                        'name' => $attributeCode,
                        'type' => $type,
                        'attributes' => $attributes,
                        'options' => $options
                    );
                    $formData[] = $fieldData;
                }
            }
        }

        return $formData;
    }

}
