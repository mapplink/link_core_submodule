<?php
/**
 * Order item entity *
 * @category Entity
 * @package Entity\Wrapper
 * @author Seo Yao
 * @author Andreas Gerhards <andreas@lero9.co.nz>
 * @copyright Copyright (c) 2014 LERO9 Ltd.
 * @license Commercial - All Rights Reserved
 */

namespace Entity\Wrapper;

use Entity\Entity;


class Creditmemoitem extends AbstractWrapper
{

    /**
     * Get product name
     * @return string
     */
    public function getProductName()
    {
        if ($product = $this->getProduct()) {
            $productName = $product->getData('name');
            return $productName;
        }
    }

    /**
     * Get quantity
     * @return int
     */
    public function getQuantity()
    {
        return (int) $this->getData('qty', 0);
    }

    /**
     * Get product entity
     * @return \Entity\Wrapper\Product
     */
    public function getProduct()
    {
        return $this->resolve('product', 'product');
    }

    public function getOrderItem()
    {
        return $this->resolve('order_item', 'orderitem');
    }

    /**
     * Get sku for the orderitem
     */
    public function getSku()
    {   
        $sku = $this->getData('sku');

        if ((!$sku) && ($product = $this->getProduct())) {
            $sku = $product->getId();
        }

        if (!$sku) {
            $sku = $this->getId();
        }

        return $sku;
    }

    /**
     * Get order
     * @return \Entity\Wrapper\Order
     */
    public function getCreditmemo()
    {   
        return $this->getParent();
    }

    /**
     * Get discounted row total
     * @return float
     */
    public function getDiscountedRowTotal()
    {
        if (!($discountedTotal = $this->getData('row_total', FALSE))) {
            $orderItem = $this->getOrderItem();
            $discountedTotal = $this->getQuantity() * $orderItem->getDiscountedPrice();
        }

        return $discountedTotal;
    }

}