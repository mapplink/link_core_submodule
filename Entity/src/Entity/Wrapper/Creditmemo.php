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

    /**
     * Get cash refund
     * @return array|null|string
     */
    public function getCashRefund()
    {
        $cashRefund = $this->getData('adjustment_positive'); // - $this->getData('adjustment_negative');
        return (float) $cashRefund;
    }
    
}