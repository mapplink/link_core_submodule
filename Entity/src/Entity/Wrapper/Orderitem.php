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
        return (int) $this->getData('quantity');
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
        $singlePrice = $this->getData('item_price');
        return $singlePrice * (int) $this->getData('quantity');
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
    public function isInStock(){
        if(!$this->getProduct()){
            return false;
        }
        $stockitem = $this->getEavService()->loadEntity($this->getLoadedNodeId(), 'stockitem', $this->getProduct()->getStoreId(), $this->getProduct()->getUniqueId());
        if(!$stockitem){
            return false;
        }

        return ($stockitem->getData('available', 0) >= $this->getData('quantity', 0));
    }

}