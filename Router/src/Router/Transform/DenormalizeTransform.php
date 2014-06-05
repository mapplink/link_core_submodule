<?php

namespace Router\Transform;

/**
 * A transform to copy data from a foreign key referenced Entity to this Entity.
 *
 * Example: Copy custom product attribute on to order item, src_attribute = product, dest_attribute=custname, foreign_type=product, foreign_att=custname
 *
 * @package Router\Transform
 */
class DenormalizeTransform extends AbstractTransform {

    /**
     * @var \Entity\Entity The foreign entity (resolved)
     */
    protected $_foreignEntity;
    /**
     * @var string The attribute to pull from the foreign entity
     */
    protected $_foreignAtt;

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

        $entityType = $this->_transformEntity->getSimpleData('foreign_type');
        $this->_foreignAtt = $this->_transformEntity->getSimpleData('foreign_att');

        if(!$entityType || !$this->_foreignAtt){
            return false; // Insufficient data
        }

        $entityType = $this->_entityConfigService->parseEntityType($entityType);
        if(!$entityType){
            return false; // Bad entity type
        }

        $fkeyAtt = $this->getSourceAttribute();


        $this->_foreignEntity = $this->_entity->resolve($fkeyAtt['code'], $entityType);
        if($this->_foreignEntity === null){
            return true; // We know result will be null, no need to check further.
        }

        if(!$this->_foreignEntity->hasAttribute($this->_foreignAtt)){
            // Ensure we have the attribute we need in the new entity
            $this->_entityService->enhanceEntity(false, $this->_foreignEntity, array($this->_foreignAtt));
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

        $data = null;
        if($this->_foreignEntity !== null){
            $data = $this->_foreignEntity->getData($this->_foreignAtt);
        }

        return array($dest['code']=>$data);
    }

}