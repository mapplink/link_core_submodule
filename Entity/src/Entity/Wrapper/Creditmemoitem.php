<?php
/**
 * Order item entity *
 * @category Magelink
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
     * Get name of product from the creditmemoitem
     * @return string
     */
    public function getName()
    {
        $name = $this->getData('name');
        return $name;
    }

    /**
     * Get orderitem
     * @return Entity
     */
    public function getOrderitem()
    {
        return $this->resolve('order_item', 'orderitem');
    }

    /**
     * Get product entity
     * @return \Entity\Wrapper\Product
     */
    public function getProduct()
    {
        return $this->resolve('product', 'product');
    }

    /**
     * Get product name
     * @return string
     */
    public function getProductName()
    {
        if ($product = $this->getProduct()) {
            $productName = $product->getData('name');
        }else{
            $productName = $this->getName();
        }

        return $productName;
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
     * Get discounted row total
     * @return float
     */
    public function getDiscountedRowTotal()
    {
        if (!($discountedTotal = $this->getData('row_total', 0) - $this->getData('discount_amount', 0))) {
            /** @var \HOPS\Wrapper\Orderitem $orderItem */
            $orderItem = $this->getOrderitem();
            $discountedTotal = $this->getQuantity() * $orderItem->getDiscountedPrice();
        }

        return $discountedTotal;
    }

    /**
     * Get creditmemo
     * @return \Entity\Wrapper\Creditmemo
     */
    public function getCreditmemo()
    {
        return $this->getParent();
    }

    /**
     * Get order
     * @return Order
     */
    public function getOrder()
    {
        return $this->getCreditmemo()->getOrder();
    }

    /**
     * Get original order
     * @return Order
     */
    public function getOriginalOrder()
    {
        return $this->getCreditmemo()->getOriginalOrder();
    }

}
