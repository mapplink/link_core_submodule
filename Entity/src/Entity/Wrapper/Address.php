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
    protected $_isoCountryMap = null;
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
        
        // Eliminate ambiguous line endings Split street information in multiple lines, if necessary and store it as an array
        $streetInfo =str_replace("\r\n", "\n", $this->getData('street'));
        if (strpos($streetInfo, "\n") === FALSE) {
            $streetArray = array($streetInfo);
        }else{
            $streetArray = explode("\n", $streetInfo);
        }

        $addressArray = array (
            $this->getData('first_name').' '.$this->getData('middle_name').' '.$this->getData('last_name'),
            $this->getData('company')
        );
        foreach ($streetArray as $streetInfo) {
            $addressArray[] = $streetInfo;
        }
        if (in_array($this->getData('country_code'), array('NZ','AU'))) {
            $addressArray[] = $this->getData('city'). ' ' . $this->getData('postcode');
        } else {
            $addressArray[] = $this->getData('city');
        }
        
        $addressArray[] = $this->getData('region');
        
        if (!in_array($this->getData('country_code'), array('NZ','AU'))) {
            $addressArray[] = $this->getCountryFromCode($this->getData('country_code'), true). ' ' . $this->getData('postcode');
        } else {
            $addressArray[] = $this->getCountryFromCode($this->getData('country_code'), true);
        }

        foreach ($addressArray as $part) {
            $part = trim($part);
            if ($part) {
                $addressParts[] = $part;
            }
        }

        return $addressParts;

    }

    /**
     * Get a country from the iso2 country code map,
     * format country to uppercase if required
     * 
     * @param string $countryCode
     * @param boolean $format
     * @return string $country
     */
    protected function getCountryFromCode($countryCode, $format=false) {
        
        $country = $countryCode;
        
        if (!$this->_isoCountryMap) {
            $config = $this->getServiceLocator()->get('Config');

            // Map the country code to a coutnry name if we have it in the module configuration
            if (key_exists('country_iso2_mapping', $config)) {
                $this->_isoCountryMap = $config['country_iso2_mapping'];
            }
        }
        if ($this->_isoCountryMap && key_exists($countryCode, $this->_isoCountryMap)) {
            $country = $this->_isoCountryMap[$country];
        }
        
        if ($format) {
            switch ($countryCode)
            {
                case 'NZ':
                    break;

                default:
                    $country = strtoupper($country);
                    break;
            }
        }
        return $country;
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