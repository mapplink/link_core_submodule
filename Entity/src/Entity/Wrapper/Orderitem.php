<?php
namespace Entity\Wrapper;

use Entity\Entity;

/*
 * Order item entity
 */
class Orderitem extends AbstractWrapper
{

    /**
     * Get product name
     * @return string
     */
    public function getProductName()
    {
        if ($product = $this->getProduct()) {
            $productName = $product->getData('name');
            return $productName;
        }
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
     * Get order
     * @return \Entity\Wrapper\Order
     */
    public function getOrder()
    {   
        return $this->getParent();
    }

    /**
     * Get total price for item
     * @return float
     */
    public function getTotalPrice()
    {
        $calculatedTotal = $this->getData('item_price') * (int) $this->getData('quantity');
        $totalPrice = $this->getData('total_price');
        return $totalPrice;
    }

    /**
     * Get row total = total_price - total_discount
     * @return float
     */
    public function getRowTotal()
    {
        $rowTotal = $this->getTotalPrice() - $this->getData('total_discount');
        return $rowTotal;
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
     * Returns whether this order item is "in stock"
     * @return bool
     */
    public function isInStock()
    {
        if (!$this->getProduct()) {
            return FALSE;
        }
        $stockitem = $this->getEavService()->loadEntity(
            $this->getLoadedNodeId(),
            'stockitem',
            $this->getProduct()->getStoreId(),
            $this->getProduct()->getUniqueId()
        );

        if(!$stockitem){
            return FALSE;
        }

        return ($stockitem->getData('available', 0) >= $this->getData('quantity', 0));
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

}