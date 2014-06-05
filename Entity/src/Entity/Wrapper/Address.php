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

/**
 * Address entity
 */
class Address extends AbstractWrapper
{   
    /**
     * Get short address 
     * @return string
     */
    public function getAddressShort()
    {
        return $this->getData('city') . ', ' . $this->getData('country_code');
    }

    public function getAddressFullArray()
    {   
        $addressParts = array();
        foreach (array (
            $this->getData('first_name') . ' ' . $this->getData('middle_name') . ' ' . $this->getData('last_name'),
            $this->getData('company'),
            $this->getData('street'),
            $this->getData('region'),
            $this->getData('city'),
            $this->getData('country_code') . ' ' . $this->getData('postcode')
        ) as $part) {
            $part = trim($part);
            if ($part) {
                $addressParts[] = $part;
            }
        }

        return $addressParts;

    }

    /**
     * Get full address 
     * @return string
     */
    public function getAddressFull($separator="<br/>")
    {   
        return implode($separator, $this->getAddressFullArray());
    }
}