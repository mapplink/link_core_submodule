<?php
/**
 * Entity\Wrapper\Creditmemo
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


class Creditmemo extends AbstractWrapper
{
    /**
     * Retrieve all the order items attached to this order
     * @return \Entity\Wrapper\Orderitem[]
     */
    public function getItems()
    {
        return $this->getCreditmemoitems();
    }

    /**
     * Retrieve all the order items attached to this order
     * @return \Entity\Wrapper\Orderitem[]
     */
    public function getCreditmemoitems()
    {
        return $this->getChildren('creditmemoitem');
    }

    /**
     * Get cash refund
     * @return array|null|string
     */
    public function getCashRefund()
    {
        $cashRefund = $this->getData('adjustment_positive', 0) - $this->getData('adjustment_negative', 0);
        return (float) $cashRefund;
    }

    /**
     * Get non cash refund
     * @return array|null|string
     */
    public function getNonCashRefund()
    {
        $nonCashRefund = $this->getData('customer_balance_ref');
        return (float) $nonCashRefund;
    }

    /**
     * Get shipping refund
     * @return array|null|string
     */
    public function getShippingRefund()
    {
        $shippingRefund = $this->getData('shipping_amount');
        return (float) $shippingRefund;
    }

    /**
     * Alias of getParent()
     * @return \Entity\Wrapper\Order|NULL $order
     */
    public function getOrder()
    {
        return $this->getParent();
    }

    /**
     * Get the uppermost original order
     * @return \Entity\Wrapper\Order|NULL $order
     */
    public function getOriginalOrder()
    {
        return $this->getOrder()->getOriginalOrder();
    }

    /**
     * Alias of getOriginalOrder()
     */
    public function getOriginalParent()
    {
        return $this->getOriginalOrder();
    }

}