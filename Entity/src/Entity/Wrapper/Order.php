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
    protected $_cachedSegregatedOrders = array();

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
     * Retrieve all direct assigned credit memos
     * @return \Entity\Wrapper\Creditmemo[]
     */
    public function getCreditmemos()
    {
        return $this->getChildren('creditmemo');
    }

    /**
     * Retrieve all direct assigned credit memo items
     * @return \Entity\Wrapper\Creditmemoitems[]
     */
    public function getCreditmemoItems()
    {
        $items = array();
        foreach ($this->getCreditmemos() as $creditmemo) {
            $items = array_merge($items, $creditmemo->getCreditmemoItems());
        }

        return $items;
    }

    /**
     * Determine if this is a root original order
     * @return (bool) $isRootOriginal
     */
    protected function isRootOriginalOrder()
    {
        if ($this->getData('original_order', FALSE)) {
            $isRootOriginal = FALSE;
        }else{
            $isRootOriginal = TRUE;
        }
        return $isRootOriginal;
    }

    /**
     * Retrieve all orders, if this is an original order
     * @return \Entity\Wrapper\Order[]
     */
    public function getOriginalChildOrders()
    {
        if ($this->isRootOriginalOrder() && !count($this->_cachedSegregatedOrders)) {
            /** @var \Entity\Service\EntityService $entityService */
            $entityService = $this->getServiceLocator()->get('entityService');

            $this->_cachedSegregatedOrders = $entityService->loadSegregatedOrders($this->getLoadedNodeId(), $this);
        }

        return $this->_cachedSegregatedOrders;
    }

    /**
     * Retrieve all credit memo assigned to the order
     * @return \Entity\Wrapper\Creditmemo[]
     */
    public function getAllCreditmemos()
    {
        $creditmemos = $this->getCreditmemos();
        foreach ($this->getOriginalChildOrders() as $order) {
            $creditmemos = array_merge($creditmemos, $order->getCreditmemos());
        }

        return $creditmemos;
    }

    /**
     * Retrieve all credit memo items assigned to the order
     * @return array
     */
    public function getAllCreditmemoItems()
    {
        $creditmemoitems = array();
        foreach ($this->getAllCreditmemos() as $creditmemo) {
            $creditmemoitems = array_merge($creditmemoitems, $creditmemo->getCreditmemoItems());
        }

        return $creditmemoitems;
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
            $order = $this->getServiceLocator()->get('entityService')
                ->loadEntityId($this->getLoadedNodeId(), $order->getData('original_order'));
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
        /** @var \Entity\Service\EntityService $entityService */
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
        $quantities = $this->getQuantities($this->getOrderItems());
        $quantity = array_sum($quantities);

        return (int) $quantity;
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

    /**
     * Get quantities in an array[<item id>] = <quantity>
     * @param $items
     * @return int[]
     */
    protected function getQuantities($items)
    {
        $quantities = array();
        foreach ($items as $item) {
            $quantities[$item->getId()] = $item->getQuantity();
        }

        return $quantities;
    }

    /**
     * Get quantities of direct assigned credit memo items
     * @return int[]
     */
    public function getCreditmemoItemsQuantityGroupedByItemId()
    {
        return $this->getQuantities($this->getAllCreditmemoItems());
    }

    /**
     * Get quantities of all credit memo items assigned to the order
     * @return int[]
     */
    public function getAllCreditmemoItemsQuantityGroupedByItemId()
    {
        return $this->getQuantities($this->getAllCreditmemoItems());
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
     * Get Aggregated Items Refunds
     * @return float
     */
    public function getItemsRefunds()
    {
        $creditmemoitems = $this->getAllCreditmemoItems();

        $itemsRefundAmount = 0;
        foreach ($creditmemoitems as $item) {
            $itemsRefundAmount += $item->getRowTotal();
        }

        return $itemsRefundAmount;
    }

    /**
     * Get Aggregated Cash Refunds
     * @return float
     */
    public function getCashRefunds()
    {
        $creditmemos = $this->getAllCreditmemos();

        $cashRefundAmount = 0;
        foreach ($creditmemos as $creditmemo) {
            $cashRefundAmount += $creditmemo->getCashRefund();
        }

        return $cashRefundAmount;
    }

    /**
     * Get Aggregated Non Cash Refunds
     * @return float
     */
    public function getNonCashRefunds()
    {
        $creditmemos = $this->getAllCreditmemos();

        $nonCashRefundAmount = 0;
        foreach ($creditmemos as $creditmemo) {
            $nonCashRefundAmount += $creditmemo->getNonCashRefund();
        }

        return $nonCashRefundAmount;
    }

    /**
     * Get Aggregated Shipping Refunds
     * @return float
     */
    public function getShippingRefunds()
    {
        $creditmemos = $this->getAllCreditmemos();

        $nonCashRefundAmount = 0;
        foreach ($creditmemos as $creditmemo) {
            $nonCashRefundAmount += $creditmemo->getShippingRefund();
        }

        return $nonCashRefundAmount;
    }

}
