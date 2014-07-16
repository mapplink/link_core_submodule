<?php
/**
 * Entity\Wrapper\Order
 *
 * @category Entity
 * @package Entity\Wrapper
 * @author Seo Yao
 * @author Andreas Gerhards <andreas@lero9.co.nz>
 * @copyright Copyright (c) 2014 LERO9 Ltd.
 * @license Commercial - All Rights Reserved
 */

namespace Entity\Wrapper;

use Entity\Entity;
use Magelink\Exception\MagelinkException;


class Order extends AbstractWrapper
{

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
     * Get the uppermost original order
     * @return \Entity\Wrapper\Order|NULL $order
     */
    public function getRootOriginal()
    {
        $order = $this;
        while ($order->getData('original_order', FALSE)) {
            $order = $this->_entityService->loadEntityId(
                $this->_nodeEntity->getNodeId(), $order->getData('original_order'));
        }

        return $order;
    }

    /**
     * Returns the sum quantity of all order items
     * @return int
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
     * Returns the sum delivery quantity of all order items
     * @return int
     * @throws MagelinkException
     */
    public function getOrderItemsTotalDeliveryQuantity()
    {
        $quantity = 0;
        foreach ($this->getOrderItems() as $orderItem) {
            $quantity += $orderItem->getDeliveryQuantity();
        }

        return (int) $quantity;
    }

    /**
     * Returns whether or not this order is fully in stock
     * @return bool
     */
    public function isInStock()
    {
        foreach ($this->getOrderItems() as $item) {
            if (!$item->isInStock()) {
                return FALSE;
            }
        }
        return TRUE;
    }

    /**
     * @return array|null|string
     */
    public function getPayments()
    {
        return $this->getData('payment_method');
    }

    /**
     * @return array
     */
    public function getPaymentMethods()
    {
        /** @var \Entity\Service\EntityService */
        $entityService = $this->getServiceLocator()->get('entityService');

        return $entityService->getPaymentMethods($this);
    }

    /**
     * @return string $methodsString
     */
    public function getPaymentMethod()
    {
        $methodsString = trim(implode(', ', $this->getPaymentMethods()));
        return $methodsString;
    }

    /**
     * @return mixed
     */
    public function getPaymentCcTypes()
    {
        /** @var \Entity\Service\EntityService */
        $entityService = $this->getServiceLocator()->get('entityService');

        return $entityService->getPaymentCcTypes($this);
    }

    /**
     * Get Aggregated Cash Refunds
     * @param array $orderItems
     * @return array
     */
    public function getCashRefunds()
    {
        $creditmemos = $this->getChildren('creditmemo');
        $cashRefundAmount = 0;
        foreach ($creditmemos as $creditmemo) {
            $cashRefundAmount += $creditmemo->getCashRefund();
        }

        return $cashRefundAmount;
    }

    /**
     * Get Aggregated Non Cash Refunds
     * @param array $orderItems
     * @return array
     */
    public function getNonCashRefunds()
    {
        $creditmemos = $this->getChildren('creditmemo');
        $nonCashRefundAmount = 0;
        foreach ($creditmemos as $creditmemo) {
            $nonCashRefundAmount += $creditmemo->getNonCashRefund();
        }

        return $nonCashRefundAmount;
    }

    /**
     * Get Aggregated Shipping Refunds
     * @param array $orderItems
     * @return array
     */
    public function getShippingRefunds()
    {
        $creditmemos = $this->getChildren('creditmemo');
        $nonCashRefundAmount = 0;
        foreach ($creditmemos as $creditmemo) {
            $nonCashRefundAmount += $creditmemo->getShippingRefund();
        }

        return $nonCashRefundAmount;
    }

    /**
     * Get Credit Memo Items Quantities of Order Items
     * @param array $orderItems
     * @return array
     */
    public function getCreditmemoItemsQuantityGroupedByOrderItemId()
    {
        $orderItems = $this->getChildren('orderitem');
        $quantities = array();
        foreach ($orderItems as $orderItem) {
            $alreadyRefundedQuantity = $orderItem->getQuantityRefunded();
            $quantities[$orderItem->getId()] = (int) $alreadyRefundedQuantity;
        }

        return $quantities;
    }

}