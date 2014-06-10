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
class Creditmemo extends AbstractWrapper
{
    /**
     * Retrieve all the order items attached to this order
     * @return \Entity\Wrapper\Orderitem[]
     */
    public function getItems()
    {
        return $this->getCreditmemoItems();
    }

    /**
     * Retrieve all the order items attached to this order
     * @return \Entity\Wrapper\Orderitem[]
     */
    public function getCreditmemoItems()
    {
        return $this->getChildren('creditmemoitem');
    }
    
}