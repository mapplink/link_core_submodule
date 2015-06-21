<?php
/**
 * Entity\Wrapper\Order
 * @category Magelink
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

    /** @var Order $_cachedOriginalOrder */
    protected $_cachedOriginalOrder;
    /** @var Order[] $_cachedAllOrders */
    protected $_cachedAllOrders = array();
    /** @var Order[] $_cachedSegregatedOrders */
    protected $_cachedSegregatedOrders = array();
    /** @var \Entity\Wrapper\Creditmemoitem[] $_cachedCreditmemos */
    protected $_cachedCreditmemoitems = array();

    /** @var float $_cachedOrderTotal */
    protected $_cachedOrderTotal = 0;


    /**
     * Alias of getOrderitems: Retrieve all the order items attached to this order
     * @return Orderitem[] $this->_cachedOrderitems
     */
    public function getItems()
    {
        return $this->getOrderitems();
    }

    /**
     * Retrieve all the order items attached to this order
     * @return Orderitem[] $this->_cachedOrderitems
     */
    public function getOrderitems($refresh = FALSE)
    {
        return $this->getChildren('orderitem', $refresh);
    }

    /**
     * Retrieve all direct assigned credit memos
     * @return Creditmemo[] $this->_cachedCreditmemos
     */
    public function getCreditmemos()
    {
        return $this->getChildren('creditmemo');
    }

    /**
     * Retrieve all direct assigned credit memo items
     * @return Creditmemoitems[] $this->_cachedCreditmemoitems
     */
    public function getCreditmemoitems()
    {
        if (!$this->_cachedCreditmemoitems) {
            foreach ($this->getCreditmemos() as $creditmemo) {
                $this->_cachedCreditmemoitems =
                    array_merge($this->_cachedCreditmemoitems, $creditmemo->getCreditmemoitems());
            }
        }

        return $this->_cachedCreditmemoitems;
    }

    /**
     * Determine if this is a original order
     * @return (bool) $isOriginalOrder
     */
    public function isOriginalOrder()
    {
        $originalOrder = $this->getData('original_order', FALSE);
        return !(bool) $originalOrder;
    }

    /**
     * Is this an segregated order?
     * @return bool $isSegregatedOrder
     */
    public function isSegregated()
    {
        return !$this->isOriginalOrder();
    }

    /**
     * Get original order id
     * @return int|string $originalOrderId
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
     * @return Order|NULL
     */
    public function getOriginalOrder()
    {
        if (!$this->_cachedOriginalOrder) {
            if ($this->isOriginalOrder()) {
                $this->_cachedOriginalOrder = $this;
            }else{
                $this->_cachedOriginalOrder = $this->_entityService
                    ->loadEntityId($this->getLoadedNodeId(), $this->getOriginalOrderId());
            }
        }

        return $this->_cachedOriginalOrder;
    }

    /**
     * Retrieve all orders, if this is an original order
     * @return Order[] $segregatedOrders
     */
    public function getSegregatedOrders($getSegregatedOrdersEvenIsNoOriginalOrder = FALSE)
    {
        $nodeId = $this->getLoadedNodeId();
        $segregatedOrders = array();

        if ($this->_cachedSegregatedOrders) {
            $segregatedOrders = $this->_cachedSegregatedOrders;
        }else{
            $this->_cachedSegregatedOrders = $this->_entityService
                ->loadSegregatedOrders($this->getLoadedNodeId(), $this);
            if (!$this->isOriginalOrder() && $getSegregatedOrdersEvenIsNoOriginalOrder) {
                if (!$this->_cachedSegregatedOrders) {
                    $segregatedOrders = $this->_entityService
                        ->loadSegregatedOrders($this->getLoadedNodeId(), $this->getOriginalOrder());
                }else{
                    $message = 'Inconsistent data: Found segregated orders on the non-original order '
                        .$this->getUniqueId().' (id: '.$this->getId().').';
                    throw new MagelinkException($message);
                }
            }else{
                $segregatedOrders = $this->_cachedSegregatedOrders;
            }
        }

        return $segregatedOrders;
    }

    /**
     * Get all order which belong to the same original order inclusive this one
     * @return Order[] $this->_cachedAllOrders
     */
    public function getAllOrders()
    {
        if (!$this->_cachedAllOrders) {
            $order = $this->getOriginalOrder();
            $this->_cachedAllOrders = array_merge(array($order), $order->getSegregatedOrders(TRUE));
        }

        return $this->_cachedAllOrders;
    }

    /**
     * Retrieves all order items of the original order and the segregated orders
     * @return Orderitem[] $orderitems
     */
    public function getOriginalOrderitems()
    {
        $orderitems = array();
        foreach ($this->getAllOrders() as $order) {
            $orderitems = array_merge($orderitems, $order->getOrderitems());
        }

        return $orderitems;
    }

    /**
     * Retrieve all credit memos assigned to the original order and the segregated orders
     * @return Creditmemo[] $creditmemos
     */
    public function getOriginalCreditmemos()
    {
        $creditmemos = array();
        foreach ($this->getAllOrders() as $order) {
            $creditmemos = array_merge($creditmemos, $order->getCreditmemos());
        }

        return $creditmemos;
    }

    /**
     * Retrieve all credit memo items assigned to the original order and the segregated orders
     * @return Creditmemoitems[] $creditmemoitems
     */
    public function getOriginalCreditmemoitems()
    {
        $creditmemoitems = array();
        foreach ($this->getOriginalCreditmemos() as $creditmemo) {
            $creditmemoitems = array_merge($creditmemoitems, $creditmemo->getCreditmemoitems());
        }

        return $creditmemoitems;
    }

    /**
     * Get the entity class of the shipping address
     * @return Address $address
     */
    public function getBillingAddressEntity()
    {
        $address = $this->resolve('billing_address', 'address');
        return $address;
    }

    /**
     * Get the entity class of the shipping address
     * @return Address $address
     */
    public function getShippingAddressEntity()
    {
        $address = $this->resolve('shipping_address', 'address');
        return $address;
    }

    /**
     * Get short shipping address
     * @return string $addressShort
     */
    public function getShippingAddressShort()
    {
        if ($address = $this->getShippingAddressEntity()) {
            $addressShort = $address->getAddressShort();
        }else{
            $addressShort = '';
        }

        return $addressShort;

    }

    /**
     * Get full shipping address
     * @return string $addressFull
     */
    public function getShippingAddressFull($separator="<br/>")
    {
        if ($address = $this->getShippingAddressEntity()) {
            $addressFull = $address->getAddressFull($separator);
        }else{
            $addressFull = '';
        }

        return $addressFull;
    }

    /**
     * Get full billing address
     * @return string $addressFull
     */
    public function getBillingAddressFull($separator="<br/>")
    {
        if ($address = $this->getBillingAddressEntity()) {
            $addressFull = $address->getAddressFull($separator);
        }else{
            $addressFull = '';
        }

        return $addressFull;
    }

    /**
     * Get the uppermost original order
     * @return Order|NULL $order
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
     * @return int $orderitemsTotalQuantity
     * @throws MagelinkException
     */
    public function getOrderitemsTotalQuantity()
    {
        $totalItemAggregate = $this->_entityService->aggregateEntity(
            $this->getLoadedNodeId(), 'orderitem', FALSE,
            array('quantity'=>'SUM'),
            array('PARENT_ID'=>$this->getId()),
            array('PARENT_ID'=>'eq'));
        if (!array_key_exists('agg_quantity_sum', $totalItemAggregate)) {
            throw new MagelinkException('Invalid response from aggregateEntity');
            $quantity = NULL;
        }else{
            $quantity = (int) $totalItemAggregate['agg_quantity_sum'];
        }

        return $quantity;
    }

    /**
     * Returns the sum delivery quantity of all order items
     * @return int $orderitemsTotalDeliveryQuantity
     * @throws MagelinkException
     */
    public function getOrderitemsTotalDeliveryQuantity()
    {
        $quantities = $this->getOrderitemsDeliveryQuantities();
        $quantity = (int) array_sum($quantities);

        return $quantity;
    }

    /**
     * Returns the sum refunded quantity of all order items
     * @return int $orderitemsTotalRefundedQuantity
     * @throws MagelinkException
     */
    public function getOrderitemsTotalRefundedQuantity()
    {
        $quantity = intval($this->getOrderitemsTotalQuantity() - $this->getOrderitemsTotalDeliveryQuantity());
        return $quantity;
    }

    /**
     * Get credit memo items quantities of order items
     * @return array $creditmemoitemsQuantityGroupedByOrderItemId
     */
    public function getCreditmemoitemsQuantityGroupedByOrderItemId()
    {
        $quantities = array();
        foreach ($this->getOrderitems() as $orderitem) {
            $alreadyRefundedQuantity = $orderitem->getQuantityRefunded();
            $quantities[$orderitem->getId()] = (int) $alreadyRefundedQuantity;
        }

        return $quantities;
    }

    /**
     * Get delivery quantities in an array[<item id>] = <quantity>
     * @return int[] $orderitemsDeliveryQuantities
     */
    protected function getOrderitemsDeliveryQuantities()
    {
        $quantities = array();
        foreach ($this->getOrderitems() as $orderitem) {
            $quantities[$orderitem->getId()] = $orderitem->getDeliveryQuantity();
        }

        return $quantities;
    }

    /**
     * Get quantities in an array[<item id>] = <quantity>
     * @param array $items
     * @return int[] $quantitiesByItemId
     */
    protected function getQuantities(array $items)
    {
        $quantities = array();
        foreach ($items as $item) {
            $quantities[$item->getId()] = $item->getQuantity();
        }

        return $quantities;
    }

    /**
     * Get quantities of direct assigned credit memo items
     * @return int[] $creditmemoitemsQuantityGroupedByItemId
     */
    public function getCreditmemoitemsQuantityGroupedByItemId()
    {
        $quantities = $this->getQuantities($this->getOriginalCreditmemoitems());
        return $quantities;
    }

    /**
     * Get quantities of all credit memo items assigned to the order
     * @return int[] $originalCreditmemoitemsQuantityGroupedByItemId
     */
    public function getOriginalCreditmemoitemsQuantityGroupedByItemId()
    {
        $quantities = $this->getQuantities($this->getOriginalCreditmemoitems());
        return $quantities;
    }

    /**
     * Get non-cash payment codes with the label as key
     * @return array $nonCashPaymentCodesByLabel
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
     * Alias of getOriginalNonCashPayments() (All non cash payment stay with the original order.)
     * @return float $nonCashPayments
     */
    public function getNonCashPayments()
    {
        return $this->getOriginalNonCashPayments();
    }

    /**
     * Get non-cash payments total on the original order, alias of getOriginalNonCashPayments
     * @return float $originalNonCashPayments
     */
    public function getAllNonCashPayments()
    {
        return $this->getOriginalNonCashPayments();
    }

    /**
     * Get non-cash payments total on the original order (and segregated orders)
     * @return float $originalNonCashPayments
     */
    public function getOriginalNonCashPayments()
    {
        $nonCash = 0;
        foreach (self::getNonCashPaymentCodes() as $code) {
            $nonCash += $this->getOriginalOrder()->getData($code, 0);
        }

        return $nonCash;
    }

    /**
     * Get aggregated grand total of the order
     * @return float $grandTotal
     */
    public function getGrandTotal()
    {
        return $this->getData('grand_total', 0);
    }

    /**
     * Get aggregated grand total of all segregated orders (original grand total)
     * @return float $originalGrandTotal
     */
    public function getOriginalGrandTotal()
    {
        $grandTotal = 0;
        foreach ($this->getAllOrders() as $order) {
            $grandTotal += $order->getGrandTotal();
        }

        return $grandTotal;
    }

    /**
     * Get discount total as a positive number
     * @return float $discountTotal
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
        $orderitems = $this->getOriginalOrderitems();

        $originalDiscountTotal = 0;
        foreach ($orderitems as $orderitem) {
            $originalDiscountTotal += $orderitem->getTotalDiscount();
        }

        return $originalDiscountTotal;
    }

    /**
     * @return float $shippingDiscount
     */
    public function getShippingDiscount()
    {
        $shippingDiscount = max(0, $this->getDiscountTotal() - $this->getOriginalDiscountTotal());
        return $shippingDiscount;
    }

    /**
     * Get discounted shipping amount
     * @return float $discountedShipping
     */
    public function getDiscountedShippingTotal()
    {
        $discountedShipping = $this->getData('shipping_total', 0) - $this->getShippingDiscount();
        return $discountedShipping;
    }

    /**
     * Get discounted shipping amount of the original order
     * @return float $originalDiscountedShipping
     */
    public function getOriginalDiscountedShippingTotal()
    {
        $discountedShipping = 0;
        foreach ($this->getAllOrders() as $order) {
            $discountedShipping += $order->getDiscountedShippingTotal();
        }

        return $discountedShipping;
    }

    /**
     * Get order total excl. shipping
     * @return float $this->_cachedOrderTota
     */
    public function getOrderTotal()
    {
        if (!$this->_cachedOrderTotal) {
            foreach ($this->getOrderitems() as $item) {
                $this->_cachedOrderTotal += $item->getDiscountedPrice() * $item->getQuantity();
            }
        }

        return $this->_cachedOrderTotal;
    }

    /**
     * Get order total excl. shipping
     * @return float $originalOrderTotal
     */
    public function getOriginalOrderTotal()
    {
        $orderTotal = 0;
        foreach ($this->getAllOrders() as $order) {
            $orderTotal += $order->getOrderTotal();
        }

        return $orderTotal;
    }

    /**
     * Get order total incl. shipping
     * @return float $orderTotalInclShipping
     */
    public function getOrderTotalInclShipping()
    {
        $orderTotalInclShipping = $this->getOrderTotal() + $this->getDiscountedShippingTotal();
        return $orderTotalInclShipping;
    }

    /**
     * Get order total incl. shipping
     * @return float $orderTotalInclShipping
     */
    public function getOriginalOrderTotalInclShipping()
    {
        $orderTotalInclShipping = 0;
        foreach ($this->getAllOrders() as $order) {
            $orderTotalInclShipping += $order->getOrderTotalInclShipping();
        }

        return $orderTotalInclShipping;
    }

    /**
     * @return array|NULL|string $payments
     */
    public function getPayments()
    {
        return $this->getData('payment_method', array());
    }

    /**
     * @return array $paymentMethods
     */
    public function getPaymentMethods()
    {
        return $this->_entityService->getPaymentMethods($this->getPayments());
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
     * @return mixed $paymentCcTypes
     */
    public function getPaymentCcTypes()
    {
        return $this->_entityService->getPaymentCcTypes($this->getPayments());
    }

    /**
     * @return mixed $shipmentMethod
     */
    public function getShipmentMethod()
    {
        return $this->getData('shipping_method', NULL);
    }

    /**
     * @return float $totalWeight
     * @throws MagelinkException
     */
    public function getTotalWeight()
    {
        $totalWeight = 0;

        foreach ($this->getOrderitems() as $orderitem) {
            $totalWeight += $orderitem->getTotalWeight();
        }

        return $totalWeight;
    }
    /**
     * Get Aggregated Items Refunds
     * @return float $itemsRefund
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
     * @return float $cashRefunds
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
     * @return float $cashAndItemsRefunds
     */
    public function getCashAndItemsRefunds()
    {
        return $this->getCashRefunds() + $this->getItemsRefunds();
    }

    /**
     * @return float $originalCashAndItemsRefunds
     */
    public function getOriginalCashAndItemsRefunds()
    {
        $originalCashAndItemsRefunds = 0;
        foreach ($this->getAllOrders() as $order) {
            $originalCashAndItemsRefunds += $order->getCashAndItemsRefunds();
        }

        return $originalCashAndItemsRefunds;
    }
    /**
     * Get Aggregated Non Cash Refunds
     * @return float $nonCashRefunds
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
     * @return float $shippingRefundAmount
     */
    public function getShippingRefunds()
    {
        $creditmemos = $this->getOriginalCreditmemos();

        $shippingRefundAmount = 0;
        foreach ($creditmemos as $creditmemo) {
            $shippingRefundAmount += $creditmemo->getShippingRefund();
        }

        return $shippingRefundAmount;
    }

    /**
     * Get aggregated items refunds of the order and all segregated orders
     * @return float $originalItemsRefunds
     */
    public function getOriginalItemsRefunds()
    {
        $itemsRefundsAmount = 0;
        foreach ($this->getAllOrders() as $order) {
            $itemsRefundsAmount += $order->getItemsRefunds();
        }

        return $itemsRefundsAmount;
    }

    /**
     * Alias of getOriginalCashRefunds: Get aggregated cash refunds of the order and all segregated orders
     * @return float $originalCashRefunds
     */
    public function getOriginalCashRefunds()
    {
        $cashRefundsAmount = 0;
        foreach ($this->getAllOrders() as $order) {
            $cashRefundsAmount += $order->getCashRefunds();
        }

        return $cashRefundsAmount;
    }

    /**
     * Get aggregated non cash refunds of the order and all segregated orders
     * @return float $originalNonCashRefunds
     */
    public function getOriginalNonCashRefunds()
    {
        $nonCash = 0;
        foreach ($this->getAllOrders() as $order) {
            $nonCash += $order->getNonCashRefunds();
        }

        return $nonCash;
    }

    /**
     * Get aggregated shipping refunds of the order and all segregated orders
     * @return float $originalShippingRefunds
     */
    public function getOriginalShippingRefunds()
    {
        return $this->getShippingRefunds();
    }

    /**
     * Alias of getOriginalItemsRefunds: get aggregated items refunds of the order and all segregated orders
     * @return float $originalItemsRefunds
     */
    public function getAllItemsRefunds()
    {
        return $this->getOriginalItemsRefunds();
    }

    /**
     * Get aggregated cash refunds of the order and all segregated orders
     * @return float $originalCashRefunds
     */
    public function getAllCashRefunds()
    {
        return $this->getOriginalCashRefunds();
    }

    /**
     * Alias of getOriginalNonCashRefunds: Get aggregated non cash refunds of the order and all segregated orders
     * @return float $originalNonCashRefunds
     */
    public function getAllNonCashRefunds()
    {
        return $this->getOriginalNonCashRefunds();
    }

    /**
     * Alias of getOriginalShippingRefunds: Get aggregated shipping refunds of the order and all segregated orders
     * @return float $originalShippingRefunds
     */
    public function getAllShippingRefunds()
    {
        return $this->getShippingRefunds();
    }

}
