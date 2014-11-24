<?php
/**
 * Product entity
 * @category HOPS
 * @author Seo Yao
 * @author Andreas Gerhards <andreas@lero9.co.nz>
 * @copyright Copyright (c) 2014 LERO9 Ltd.
 * @license Commercial - All Rights Reserved
 */

namespace Entity\Wrapper;

use Entity\Entity;


class Product extends AbstractWrapper
{
    const TYPE_VIRTUAL = 'virtual';
    const TYPE_DOWNLOADABLE = 'downloadable';
    const TYPE_GIFTCARD = 'giftcard';

    /**
     * Check if product is shippable
     * @param $type
     * @return bool
     */
    static public function isProductTypeShippable($type)
    {
        $isNotShippableTypes = array(
            self::TYPE_VIRTUAL,
            self::TYPE_DOWNLOADABLE,
            self::TYPE_GIFTCARD
        );

        $isShippable = !in_array($type, $isNotShippableTypes);

        return $isShippable;
    }

    /**
     * @return bool
     */
    public function isShippable()
    {
        $type = $this->getData('type');
        return self::isProductTypeShippable($type);
    }
}