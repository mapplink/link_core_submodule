<?php
/**
 * Stockitem entity
 * @category Magelink
 * @author Andreas Gerhards <andreas@lero9.co.nz>
 * @copyright Copyright (c) 2016 LERO9 Ltd.
 * @license Commercial - All Rights Reserved
 */

namespace Entity\Wrapper;

use Entity\Entity;


class Stockitem extends AbstractWrapper
{

    const PRODUCT_TYPE_ID = 3;

    /**
     * Return the parent Entity ID of this Entity, or NULL if none specified.
     * @return int|NULL
     */
    public function getParentId()
    {
        $this->_parent_id = parent::getParentId();

        if (is_null($this->_parent_id)) {
            $product = $this->_entityService->loadEntity(0, self::PRODUCT_TYPE_ID, 0, $this->getUniqueId());

            if ($product instanceof Product) {
                $this->_parent_id = $product->getId();
            }
        }

        return $this->_parent_id;
    }

}
