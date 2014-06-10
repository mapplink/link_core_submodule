<?php
/**
 * HOPS
 *
 * @category    HOPS
 * @author      Sean Yao <sean@lero9.com>
 * @copyright   Copyright (c) 2014 LERO9 Ltd.
 * @license     Commercial - All Rights Reserved
 */

namespace Entity\Wrapper;

use Entity\Entity;
use Magelink\Exception\MagelinkException;

/**
 * Order entity class
 */
class Order extends AbstractWrapper
{

    public function getShippingMethodText()
    {
        $shippingMethodCode = $this->getData('shipping_method');

        $methods = self::getAllShippingMethods();

        if (array_key_exists($shippingMethodCode, $methods)) {
            return $methods[$shippingMethodCode];
        }
    }

    /**
     * Retrieve all the order items attached to this order
     * @return \Entity\Wrapper\Orderitem[]
     */
    public function getItems()
    {
        return $this->getOrderItems();
    }

    /**
     * Retrieve all the order items attached to this order
     * @return \Entity\Wrapper\Orderitem[]
     */
    public function getOrderItems()
    {
        return $this->getChildren('orderitem');
    }

    /**
     * Get the entity class of the shipping address
     * @return \Entity\Wrapper\Address
     */
    public function getBillingAddressEntity()
    {
        return $this->resolve('billing_address', 'address');
    }

    /**
     * Get the entity class of the shipping address
     * @return \Entity\Wrapper\Address
     */
    public function getShippingAddressEntity()
    {
        return $this->resolve('shipping_address', 'address');
    }

    /**
     * Get short shipping address
     * @return string
     */
    public function getShippingAddressShort()
    {
        if ($address = $this->getShippingAddressEntity()) {
            return $address->getAddressShort();
        }
    }

    /**
     * Get full shipping address
     * @return string
     */
    public function getShippingAddressFull($separator="<br/>")
    {
        if ($address = $this->getShippingAddressEntity()) {
            return $address->getAddressFull($separator);
        }
    }

    /**
     * Get full billing address
     * @return string
     */
    public function getBillingAddressFull($separator="<br/>")
    {
        if ($address = $this->getBillingAddressEntity()) {
            return $address->getAddressFull($separator);
        }
    }

    /**
     * Returns the sum quantity of all order items
     * @return float
     * @throws MagelinkException
     */
    public function getOrderItemsTotalQty()
    {
        $entityService = $this->getServiceLocator()->get('entityService');
        
        $totalItemAggregate = $entityService->aggregateEntity(
            $this->getLoadedNodeId(), 'orderitem', FALSE,
            array('quantity'=>'SUM'),
            array('PARENT_ID'=>$this->getId()),
            array('PARENT_ID'=>'eq'));
        if (!array_key_exists('agg_quantity_sum', $totalItemAggregate)) {
            throw new MagelinkException('Invalid response from aggregateEntity');
        }
        return (int) $totalItemAggregate['agg_quantity_sum'];
    }

    /**
     * Returns whether or not this order is fully in stock
     * @return bool
     */
    public function isInStock(){
        foreach($this->getOrderItems() as $item){
            if(!$item->isInStock()){
                return false;
            }
        }
        return true;
    }
}