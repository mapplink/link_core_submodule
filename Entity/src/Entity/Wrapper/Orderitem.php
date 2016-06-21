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
use Entity\Wrapper\Product;
use Magelink\Exception\MagelinkException;
use Magento\Service\MagentoService;


class Orderitem extends AbstractWrapper
{

    /**
     * Get product name
     * @return string|NULL
     */
    public function getProductName()
    {
        if ($product = $this->getProduct()) {
            $productName = $product->getData('name', '');
        }else{
            $productName = $this->getData('product_name', '');
        }

        return $productName;
    }

    /**
     * Get quantity
     * @return int
     */
    public function getQuantity()
    {
        return (int) $this->getData('quantity', 0);
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
     * Get sku for the orderitem
     * @return string|NULL $sku
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
     * Get discounted item price
     * @return float|NULL
     */
    public function getDiscountedPrice()
    {
        $price = $this->getData('item_price', 0) - $this->getData('item_discount', 0);
        return $price;
    }

    /**
     * Get order
     * @return \Entity\Wrapper\Order
     */
    public function getOrder()
    {
        return $this->getParent();
    }

    /**
     * Get original order
     * @return \Entity\Wrapper\Order
     */
    public function getOriginalOrder()
    {
        return $this->getOrder()->getOriginalOrder();
    }

    /**
     * Get total price for item
     * @return float
     */
    public function getTotalPrice()
    {
        if (!($totalPrice = $this->getData('total_price', 0))) {
            $totalPrice = $this->getData('item_price', 0) * $this->getQuantity();
        }

        return $totalPrice;
    }

    /**
     * Get total discount as positive number
     * @return float
     */
    public function getTotalDiscount()
    {
        return abs($this->getData('total_discount', 0));
    }

    /**
     * Get total discounted price
     * @return float
     */
    public function getTotalDiscountedPrice()
    {
        $discountedTotal = $this->getTotalPrice() - $this->getData('total_discount', 0);
        return $discountedTotal;
    }

    /**
     * Get order unique id (increment id)
     * @return string
     */
    public function getOrderIncrementId()
    {
        $order = $this->getOrder();
        return $order->getUniqueId();
    }

    /**
     * Get an accumulated quantity of the associated credit memo items
     * @return
     */
    public function getQuantityRefunded()
    {
        try {
            $alreadyRefunded = $this->getServiceLocator()->get('entityService')->aggregateEntity(
                $this->getLoadedNodeId(),
                'creditmemoitem',
                $this->getStoreId(),
                array('qty'=>'SUM'),
                array('order_item' => $this->getId()),
                array('order_item' => 'eq')
            );
        }catch (\Exception $exception) {}

        if(!array_key_exists('agg_qty_sum', $alreadyRefunded)){
            throw new MagelinkException('Invalid response from aggregateEntity. '.(isset($exception) ? $exception : ''));
        }

        return (int) max(0, $alreadyRefunded['agg_qty_sum']);
    }

    /**
     * Get quantity to deliver
     * @return int
     */
    public function getDeliveryQuantity()
    {
        $deliveryQuantity = $this->getQuantity() - $this->getQuantityRefunded();
        return (int) $deliveryQuantity;
    }

    /**
     * Checks if order item is shippable
     * @return bool
     */
    public function isShippable()
    {
        /** @var \Magento\Service\MagentoService $magentoService */
        $magentoService = $this->getServiceLocator()->get('magentoService');
        $isShippable = $magentoService->isProductTypeShippable($this->getData('product_type'));

        return $isShippable;
    }

    /**
     * @return float $weight
     * @throws MagelinkException
     */
    public function getWeight()
    {
        $product = $this->getProduct();
        if ($product instanceof Product) {
            $weight = $product->getData('weight', 0);
        }else{
            $weight = 0;  // @todo : Log this
        }

        return $weight;
    }

    /**
     * @return float $totalWeight
     * @throws MagelinkException
     */
    public function getTotalWeight()
    {
        $totalWeight = $this->getWeight() * $this->getDeliveryQuantity();
        return $totalWeight;
    }

}
