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
    /** @var \Entity\Wrapper\Order[] $_cachedSegregatedOrders */
    protected $_cachedSegregatedOrders = array();

    /** @var \Entity\Wrapper\Orderitem[] $_cachedOrderitems */
    protected $_cachedOrderitems = array();

    /** @var \Entity\Wrapper\Creditmemo[] $_cachedCreditmemos */
    protected $_cachedCreditmemos = array();

    /** @var \Entity\Wrapper\Creditmemo[] $_cachedCreditmemos */
    protected $_cachedCreditmemoitems = array();


    /**
     * Retrieve all the order items attached to this order
     * @return \Entity\Wrapper\Orderitem[]
     */
    public function getItems()
    {
        return $this->getOrderitems();
    }

    /**
     * Retrieve all the order items attached to this order
     * @return \Entity\Wrapper\Orderitem[]
     */
    public function getOrderitems()
    {
        if (!$this->_cachedOrderitems()) {
            $this->_cachedOrderitems = $this->getChildren('orderitem');
        }

        return $this->_cachedOrderitems;
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
    public function getCreditmemoitems()
    {
        $items = array();
        foreach ($this->getCreditmemos() as $creditmemo) {
            $items = array_merge($items, $creditmemo->getCreditmemoitems());
        }

        return $items;
    }

    public function getAllOrders()
    {
        $order = $this->getOriginalOrder();
        $allOrders = array_merge(array($order), $order->getSegregatedOrders());

        return $allOrders;
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
     * Retrieves all order items of the original order and the segregated orders
     * @return \Entity\Wrapper\Orderitem[]
     */
    public function getOriginalOrderItems()
    {
        $orderItems = array();
        foreach ($this->getOriginalOrder()->getAllOrders() as $order) {
            $orderItems = array_merge($orderItems, $order->getOrderitems());
        }

        return $orderItems;
    }

    /**
     * Alias of getOriginalOrderItems: retrieves all order items of the original order and the segregated orders
     * @return \Entity\Wrapper\Orderitem[]
     */
    public function getAllOrderItems()
    {
        return $this->getOriginalOrderItems();
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
    public function getAllCreditmemoitems()
    {
        $creditmemoitems = array();
        foreach ($this->getAllCreditmemos() as $creditmemo) {
            $creditmemoitems = array_merge($creditmemoitems, $creditmemo->getCreditmemoitems());
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
    public function getOrderitemsTotalQuantity()
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
    public function getOrderitemsTotalDeliveryQuantity()
    {
        $quantities = $this->getOrderitemsDeliveryQuantities();
        $quantity = array_sum($quantities);

        return (int) $quantity;
    }

    /**
     * Returns the sum refunded quantity of all order items
     * @return int
     * @throws MagelinkException
     */
    public function getOrderitemsTotalRefundedQuantity()
    {
        $quantity = $this->getOrderitemsTotalQuantity() - $this->getOrderitemsTotalDeliveryQuantity();
        return (int) $quantity;
    }

    /**
     * Get Credit Memo Items Quantities of Order Items
     * @param array $orderItems
     * @return array
     */
    public function getCreditmemoitemsQuantityGroupedByOrderItemId()
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
    protected function getOrderitemsDeliveryQuantities()
    {
        $quantities = array();

        $orderItems = $this->getOrderitems();
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
    public function getCreditmemoitemsQuantityGroupedByItemId()
    {
        return $this->getQuantities($this->getAllCreditmemoitems());
    }

    /**
     * Get quantities of all credit memo items assigned to the order
     * @return int[]
     */
    public function getAllCreditmemoitemsQuantityGroupedByItemId()
    {
        return $this->getQuantities($this->getAllCreditmemoitems());
    }

    /**
     * Returns whether or not this order is fully in stock
     * @return bool
     */
    public function isInStock()
    {
        foreach ($this->getOrderitems() as $item) {
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
    public static function getNonCashPaymentCodes()
    {
        $nonCashPaymentCodes = array(
            'Gift Card Total'=>'giftcard_total',
            'Reward Points Total'=>'reward_total',
            'Store Credit Total'=>'storecredit_total'
        );
        return $nonCashPaymentCodes;

    }

    /**
     * Get non-cash payments total on this order
     * @return float
     */
    public function getNonCashPayments()
    {
        $nonCash = 0;
        foreach (self::getNonCashPaymentCodes() as $code) {
            $nonCash += $this->getData($code, 0);
        }

        return $nonCash;
    }

    /**
     * Get non-cash payments total on the original order, alias of getOriginalNonCashPayments
     * @return float
     */
    public function getAllNonCashPayments()
    {
        return $this->getOriginalNonCashPayments();
    }

    /**
     * Get non-cash payments total on the original order
     * @return float
     */
    public function getOriginalNonCashPayments()
    {
        $nonCash = $this->getOriginalOrder()->getNonCashPayments();
        return $nonCash;
    }

    /**
     * Get aggregated grand total of the order
     * @return float
     */
    public function getGrandTotal()
    {
        $grandTotal = $this->getData('grand_total', 0);
        return $grandTotal;
    }

    /**
     * Get aggregated grand total of all segregated orders (original grand total)
     * @return float
     */
    public function getOriginalGrandTotal()
    {
        $grandTotal = 0;
        foreach ($this->$this->getOriginalOrder()->getAllOrders() as $order) {
            $grandTotal += $order->getGrandTotal();
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
        foreach ($this->getOrderitems() as $item) {
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
        $orderTotal = $this->getOriginalGrandTotal() + $this->getNonCashPayments()
            - $this->getOriginalDiscountedShippingTotal();
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
     * Get order total incl. shipping
     * @return float
     */
    public function getOriginalOrderTotalInclShipping()
    {
        $orderTotalInclShipping = 0;
        foreach ($this->getOriginalOrder()->getAllOrders() as $order) {
            $orderTotalInclShipping += $order->getOrderTotalInclShipping();
        }

        return $orderTotalInclShipping;
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
     * Get total order discount excl. shipping
     * @return float $originalDiscountTotal
     */
    public function getOriginalDiscountTotal()
    {
        $originalDiscountTotal = 0;
        foreach ($this->getAllOrderItems() as $orderItem) {
            $originalDiscountTotal += $orderItem->getTotalDiscount();
        }

        return $originalDiscountTotal;
    }

    /**
     * @return float
     */
    public function getShippingDiscount()
    {
        $shippingDiscount = max(0, $this->getDiscountTotal() - $this->getOriginalDiscountTotal());
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
     * Get discounted shipping amount of the original order
     * @return float
     */
    public function getOriginalDiscountedShippingTotal()
    {
        return $this->getOriginalOrder()->getDiscountedShippingTotal();
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
    public function getPaymentMethodsString()
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
        $creditmemoitems = $this->getCreditmemoitems();

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
        $creditmemos = $this->getCreditmemos();

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
        $creditmemos = $this->getCreditmemos();

        $nonCash = 0;
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
    public function getAllItemsRefunds()
    {
        $itemsRefundsAmount = 0;
        foreach ($this->getAllOrders() as $order) {
            $itemsRefundsAmount += $order->getItemsRefunds();
        }

        return $itemsRefundsAmount;
    }

    /**
     * Get aggregated cash refunds of the order and all segregated orders
     * @return float
     */
    public function getAllCashRefunds()
    {
        $cashRefundsAmount = 0;
        foreach ($this->getAllOrders() as $order) {
            $cashRefundsAmount += $order->getCashRefunds();
        }

        return $cashRefundsAmount;
    }

    /**
     * Get aggregated non cash refunds of the order and all segregated orders
     * @return float
     */
    public function getAllNonCashRefunds()
    {
        $nonCash = 0;
        foreach ($this->getAllOrders() as $order) {
            $nonCash += $order->getNonCashRefunds();
        }

        return $nonCash;
    }

    /**
     * Get aggregated shipping refunds of the order and all segregated orders
     * @return float
     */
    public function getAllShippingRefunds()
    {
        $shippingRefundAmount = 0;
        foreach ($this->getAllOrders() as $order) {
            $shippingRefundAmount += $order->getShippingRefunds();
        }

        return $shippingRefundAmount;
    }

}
