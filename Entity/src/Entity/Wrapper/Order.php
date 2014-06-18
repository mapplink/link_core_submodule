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

    /**
     * Returns array of Payment Methods to be extracted as values
     * @return string[]
     */
    public function getRawPaymentMethods()
    {
        $paymentMethods = $this->getData('payment_method');

        if (is_array($paymentMethods)) {
            if (count($paymentMethods) <= 1) {
                $paymentMethodsArray[] = trim(  array_shift( array_keys($paymentMethods) )  );
            } else {
                foreach ( array_keys($paymentMethods) as $key ) {
                    $paymentMethodsArray[] = trim($key);
                }
            }
            return $paymentMethodsArray;
        } else {
            return array();
        }
    }

    /*use Node\AbstractNode;*/
    /*use Node\Entity;*/
/*    public function getRawOutput() {

        protected $_node;
        protected $_nodeEnt;
        protected $_soap = null;
        protected $_db = null;
        protected $_ns = null;

        $this->_node = $node;
        $this->_nodeEnt = $nodeEntity;

        $this->_soap = $node->getApi('soap');
        if(!$this->_soap){
            throw new MagelinkException('SOAP is required for Magento Orders');
        }
        $this->_db = $node->getApi('db');

        $this->_ns = $this->getServiceLocator()->get('nodeService');

        if($this->_soap) {
            $results2 = $this->_soap->call('salesOrderList', array(
                array(
                    'complex_filter'=>array(
                        array(
                            'key'=>'updated_at',
                            'value'=>array('key'=>'gt', 'value'=>$retTime),
                             ),
                        ),
                    ),
                ));
        }

        foreach ($results2 as $orderFromList) {
            $order2 = $this->_soap->call('salesOrderInfo', array($orderFromList['increment_id']));
            if (isset($order2['result'])) {
                $order2 = $order2['result'];
            }
            foreach(array_diff(array_keys($orderFromList), array_keys($order2)) as $key) {
                $order2[$key] = $orderFromList[$key];
            }
        }
        return $order2;
    }*/


/*
    public function getPaymentMethods()
    {
        $paymentMethods = $this->getData('payment_method');

        if ( is_array($paymentMethods) ) {
            return trim(  array_shift( array_keys($paymentMethods) )  );
        } else {
            return false;
        }
    }

    public function getCreditCardBool()
    {
        $ccInfo = $this->getData('payment_method');

        if ( is_array($ccInfo) ) {
            return preg_match(   '/.+{{\w+}}/i', trim(  array_shift( array_keys($ccInfo) )  )   ) ? true : false;
        } else {
            return false;
        }
    }

    public function getCreditCardInfo()
    {
        $ccInfo = $this->getData('payment_method');

        if ( is_array($ccInfo) && $this->getCreditCardBool() ) {
            return trim(  array_shift( array_keys($ccInfo) )  );
        } else {
            return '';
        }
    }

    public function getPaymentAmounts()
    {
        $paymentMethods = $this->getData('payment_method');

        if ( is_array($paymentMethods) ) {
            return $paymentMethods;
        } else {
            return array();
        }
    }*/
}