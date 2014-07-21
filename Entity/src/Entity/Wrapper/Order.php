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
    public function isOriginalOrder()
    {
        if ($this->getData('original_order', FALSE)) {
            $isOriginal = FALSE;
        }else{
            /** @var \Entity\Service\EntityService $entityService */
            $entityService = $this->getServiceLocator()->get('entityService');
            $this->_cachedSegregatedOrders = $entityService->loadSegregatedOrders($this->getLoadedNodeId(), $this);
            $isOriginal = TRUE;
        }
        return $isOriginal;
    }

    /**
     * Is this an segregated order?
     * @return bool
     */
    public function isSegregated()
    {
        return !$this->isOriginalOrder();
    }

    /**
     * Get original order id
     * @return int|string
     */
    public function getOriginalOrderId()
    {
        if ($this->isOriginalOrder()) {
            $originalOrderId = $this->getId();
        }else{
            $originalOrderId = $this->getData('original_order');
        }

        return $originalOrderId;
    }

    /**
     * Get original order entity
     * @return Entity|Order|null
     */
    public function getOriginalOrder()
    {
        if ($this->isOriginalOrder()) {
            $originalOrder = $this;
        }else{
            /** @var \Entity\Service\EntityService $entityService */
            $entityService = $this->getServiceLocator()->get('entityService');
            $originalOrder = $entityService->loadEntityId($this->getLoadedNodeId(), $this->getOriginalOrderId());
        }

        return $originalOrder;
    }

    /**
     * Retrieve all orders, if this is an original order
     * @return \Entity\Wrapper\Order[]
     */
    public function getSegregatedOrders()
    {
        $this->isOriginalOrder();
        return $this->_cachedSegregatedOrders;
    }

    /**
     * Retrieve all credit memo assigned to the order
     * @return \Entity\Wrapper\Creditmemo[]
     */
    public function getAllOrderItems()
    {
        $orderItems = $this->getOrderItems();
        foreach ($this->getSegregatedOrders() as $order) {
            $orderItems = array_merge($orderItems, $order->getOrderItems());
        }

        return $orderItems;
    }

    /**
     * Retrieve all credit memo assigned to the order
     * @return \Entity\Wrapper\Creditmemo[]
     */
    public function getAllCreditmemos()
    {
        $creditmemos = $this->getCreditmemos();
        foreach ($this->getSegregatedOrders() as $order) {
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
    public function getOriginalOrderRecursive()
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
    public function getOrderItemsTotalQuantity()
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
        $quantities = $this->getOrderItemsDeliveryQuantities();
        $quantity = array_sum($quantities);

        return (int) $quantity;
    }

    /**
     * Returns the sum refunded quantity of all order items
     * @return int
     * @throws MagelinkException
     */
    public function getOrderItemsTotalRefundedQuantity()
    {
        $quantity = $this->getOrderItemsTotalQuantity() - $this->getOrderItemsTotalDeliveryQuantity();
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
     * Get delivery quantities in an array[<item id>] = <quantity>
     * @return int[]
     */
    protected function getOrderItemsDeliveryQuantities()
    {
        $quantities = array();

        $orderItems = $this->getOrderItems();
        /** @var \Entity\Wrapper\Orderitem $orderItem */
        foreach ($orderItems as $orderItem) {
            $quantities[$orderItem->getId()] = $orderItem->getDeliveryQuantity();
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
     * Get non-cash payments total
     * @return float
     */
    public function getNonCashPayments()
    {
        $nonCash = $this->getData('giftcard_total', 0) + $this->getData('reward_total', 0)
            + $this->getData('storecredit_total', 0);
        return $nonCash;

    }

    /**
     * Get aggregated grand total of all segregated orders (original grand total)
     * @return float
     */
    public function getOriginalGrandTotal()
    {
        $grandTotal = $this->getData('grand_total', 0);
        foreach ($this->getSegregatedOrders() as $order) {
            $grandTotal += $order->getData('grand_total', 0);
        }

        return $grandTotal;
    }

    /**
     * Get order total excl. shipping
     * @return float
     */
    public function getOrderTotal()
    {
        $orderTotal = 0;

        $orderItems = $this->getOrderItems();
        foreach ($orderItems as $item) {
            $orderTotal += $item->getDiscountedPrice() * $item->getQuantity();
        }

        return $orderTotal;
    }

    /**
     * Get order total excl. shipping
     * @return float
     */
    public function getOriginalOrderTotal()
    {
        $orderTotal = $this->getOriginalGrandTotal() + $this->getNonCashPayments();
        return $orderTotal;
    }

    /**
     * Get order total incl. shipping
     * @return float
     */
    public function getOrderTotalInclShipping()
    {
        $orderTotalInclShipping = $this->getOrderTotal() + $this->getDiscountedShippingTotal();
        return $orderTotalInclShipping;
    }

    /**
     * Get total order discount excl. shipping
     * @return float $totalItemsDiscount
     */
    public function getTotalItemsDiscount()
    {
        $totalItemsDiscount = 0;

        $orderItems = $this->getAllOrderItems();
        foreach ($orderItems as $orderItem) {
            $totalItemsDiscount += $orderItem->getTotalDiscount();
        }

        return $totalItemsDiscount;
    }

    /**
     * Get discount total as a positive number
     * @return float
     */
    public function getDiscountTotal()
    {
        return abs($this->getData('discount_total', 0));
    }

    /**
     * @return float
     */
    public function getShippingDiscount()
    {
        $shippingDiscount = $this->getDiscountTotal() - $this->getTotalItemsDiscount();
        return $shippingDiscount;
    }

    /**
     * Get discounted shipping amount
     * @return float
     */
    public function getDiscountedShippingTotal()
    {
        $discountedShipping = $this->getData('shipping_total', 0) - $this->getShippingDiscount();
        return $discountedShipping;
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
            $itemsRefundAmount += $item->getDiscountedRowTotal();
        }

        return $itemsRefundAmount;
    }

    /**
     * Get aggregated cash refunds
     * @return float
     */
    public function getCashRefunds()
    {
        $creditmemos = $this->getAllCreditmemos();

        $cashRefundsAmount = 0;
        foreach ($creditmemos as $creditmemo) {
            $cashRefundsAmount += $creditmemo->getCashRefund();
        }

        return $cashRefundsAmount;
    }

    /**
     * Get Aggregated Non Cash Refunds
     * @return float
     */
    public function getNonCashRefunds()
    {
        $creditmemos = $this->getAllCreditmemos();

        $non = 0;
        foreach ($creditmemos as $creditmemo) {
            $nonCash += $creditmemo->getNonCashRefund();
        }

        return $nonCash;
    }

    /**
     * Get Aggregated Shipping Refunds
     * @return float
     */
    public function getShippingRefunds()
    {
        $creditmemos = $this->getAllCreditmemos();

        $shippingRefundAmount = 0;
        foreach ($creditmemos as $creditmemo) {
            $shippingRefundAmount += $creditmemo->getShippingRefund();
        }

        return $shippingRefundAmount;
    }

    /**
     * Get aggregated items refunds of the order and all segregated orders
     * @return float
     */
    protected function getAllItemsRefunds()
    {
        $itemsRefundsAmount = $this->getItemsRefunds();
        foreach ($this->getSegregatedOrders() as $order) {
            $itemsRefundsAmount += $order->getItemsRefunds();
        }

        return $itemsRefundsAmount;
    }

    /**
     * Get aggregated cash refunds of the order and all segregated orders
     * @return float
     */
    protected function getAllCashRefunds()
    {
        $cashRefundsAmount = $this->getCashRefunds();
        foreach ($this->getSegregatedOrders() as $order) {
            $cashRefundsAmount += $order->getCashRefunds();
        }

        return $cashRefundsAmount;
    }

    /**
     * Get aggregated non cash refunds of the order and all segregated orders
     * @return float
     */
    protected function getAllNonCashRefunds()
    {
        $nonCash = $this->getNonCashRefunds();
        foreach ($this->getSegregatedOrders() as $order) {
            $nonCash += $order->getNonCashRefunds();
        }

        return $nonCash;
    }

    /**
     * Get aggregated shipping refunds of the order and all segregated orders
     * @return float
     */
    protected function getAllShippingRefunds()
    {
        $shippingRefundAmount = $this->getShippingRefunds();
        foreach ($this->getSegregatedOrders() as $order) {
            $shippingRefundAmount += $order->getShippingRefunds();
        }

        return $shippingRefundAmount;
    }

}
