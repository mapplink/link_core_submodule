<?php

namespace Router\Transform;

/**
 * A simple transform that sets an attribute to a provided value. Source attribute used only for trigger, value ignored
 *
 * @package Router\Transform
 */
class SetTransform extends AbstractTransform {

    protected $_value = null;

    /**
     * Perform any initialization/setup actions, and check any prerequisites.
     *
     * @return boolean Whether this transform is eligible to run
     */
    protected function _init() {
        if(!$this->getDestAttribute()){
            // Ensure destination attribute configured
            return false;
        }
        $this->_value = $this->_transformEntity->getSimpleData('value');
        if(!$this->_value){
            // Value not configured
            return false;
        }
        return true;
    }

    /**
     * Apply the transform on any necessary data
     *
     * @return array New data changes to be merged into the update.
     */
    public function apply() {
        $dest = $this->getDestAttribute();

        return array($dest['code']=>$this->_value);
    }

}