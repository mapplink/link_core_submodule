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
use Magento\Service\MagentoService;


class Product extends AbstractWrapper
{
    /**
     * Checks if product is shippable
     * @return bool
     */
    public function isShippable()
    {
        /** @var \Magento\Service\MagentoService $magentoService */
        $magentoService = $this->getServiceLocator()->get('magentoService');
        $isShippable = $magentoService->isProductTypeShippable($this->getData('type'));

        return $isShippable;
    }
}