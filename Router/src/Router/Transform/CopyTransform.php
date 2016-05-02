<?php
/**
 * A simple transform that simply copies from source to destination
 * @category Router
 * @package Router\Transform
 * @author Andreas Gerhards <andreas@lero9.co.nz>
 * @copyright Copyright (c) 2014 LERO9 Ltd.
 * @license Commercial - All Rights Reserved
 */

namespace Router\Transform;


class CopyTransform extends AbstractTransform
{

    /**
     * Perform any initialization/setup actions, and check any prerequisites.
     * @return boolean Whether this transform is eligible to run
     */
    protected function _init()
    {
        if ($this->getDestAttribute()) {
            $success = TRUE;
        }else{
            $success = FALSE;
        }

        return $success;
    }

    /**
     * Apply the transform on any necessary data
     *
     * @return array New data changes to be merged into the update.
     */
    public function _apply()
    {
        $src = $this->getSourceAttribute();
        $dest = $this->getDestAttribute();

        return array($dest['code']=>$this->_entity->getData($src['code']));
    }

}
