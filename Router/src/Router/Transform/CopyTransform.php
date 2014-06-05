<?php

namespace Router\Transform;

/**
 * A simple transform that simply copies from source to destination
 *
 * @package Router\Transform
 */
class CopyTransform extends AbstractTransform {

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
        return true;
    }

    /**
     * Apply the transform on any necessary data
     *
     * @return array New data changes to be merged into the update.
     */
    public function apply() {
        $src = $this->getSourceAttribute();
        $dest = $this->getDestAttribute();

        return array($dest['code']=>$this->_entity->getData($src['code']));
    }

}