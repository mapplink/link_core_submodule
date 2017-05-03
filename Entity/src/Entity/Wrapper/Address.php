<?php
/**
 * @package Entity\Wrapper
 * @author Matt Johnston
 * @author Andreas Gerhards <andreas@lero9.co.nz>
 * @copyright Copyright (c) 2014 LERO9 Ltd.
 * @license http://opensource.org/licenses/BSD-3-Clause BSD-3-Clause - Please view LICENSE.md for more information
 */

namespace Entity\Wrapper;

use Entity\Entity;
use Entity\Service\EntityService;
use Log\Service\LogService;


class Address extends AbstractWrapper
{

    /** @var array|null */
    protected $isoCountryMap = NULL;


    /**
     * @param int $nodeId
     * @return array $customers
     */
    public function getRelatedCustomerEntities($nodeId)
    {
        /** @var EntityService $entityService */
        $entityService = $this->getServiceLocator()->get('entityService');

        $entityId = $this->getId();
        $customersWithThisBillingAddress = $entityService->locateEntity($nodeId, 'customer', FALSE,
            array('billing_address'=>$entityId));
        $customersWithThisShippingAddress = $entityService->locateEntity($nodeId, 'customer', FALSE,
            array('shipping_address'=>$entityId));
        $customers = array_merge($customersWithThisBillingAddress, $customersWithThisShippingAddress);

        return $customers;
    }

    /**
     * @return string $shortAddress
     */
    public function getAddressShort()
    {
        $address =  $this->getData('city').', '.$this->getData('country_code');
        return $address;
    }

    /**
     * @return array $fullAddressArray
     * @throws \Magelink\Exception\MagelinkException
     */
    public function getAddressFullArray()
    {
        $addressParts = array();

        // Eliminate ambiguous line endings
        $streetInfo = str_replace("\r\n", "\n", $this->getData('street'));

        // Fixes arrays on the street field (which should actually never occur) and convert them into string
        if (is_array($streetInfo)) {
            $streetInfo = array_pop($streetInfo);
        }

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
     * Get a country from the iso2 country code map, format country to uppercase if required
     * @param string $countryCode
     * @param boolean $format
     * @return string $country
     */
    protected function getCountryFromCode($countryCode, $format = FALSE)
    {
        $country = $countryCode;

        if (!$this->isoCountryMap) {
            $config = $this->getServiceLocator()->get('Config');

            // Map the country code to a coutnry name if we have it in the module configuration
            if (array_key_exists('country_iso2_mapping', $config)) {
                $this->isoCountryMap = $config['country_iso2_mapping'];
            }
        }
        if ($this->isoCountryMap && key_exists($countryCode, $this->isoCountryMap)) {
            $country = $this->isoCountryMap[$country];
        }

        if ($format) {
            switch ($countryCode)
            {
                case 'NZ':
                    break;
                default:
                    $country = strtoupper($country);
            }
        }
        return $country;
    }

    /**
     * @return string $fullAddress
     */
    public function getAddressFull($separator = '<br/>')
    {
        return implode($separator, $this->getAddressFullArray());
    }

    /**
     * @return array $streetSuburb
     */
    public function getStreetSuburbArray()
    {
        $streetSuburb = array('street'=>'', 'suburb'=>'');

        $street = $this->getData('street', '');
        if (is_array($street)) {
            $street = implode(chr(10), $street);
            $this->getServiceLocator()->get('logService')->log(LogService::LEVEL_ERROR,
                'ety_add_str_err',
                'Street attribute is array.',
                array('entity id'=>$this->getEntityId(), 'entity unique'=>$this->getUniqueId())
            );
        }
        $addressArray = explode(chr(10), $street);

        if (count($addressArray) == 1) {
            $streetSuburb['street'] = array_shift($addressArray);
        }elseif (count($addressArray) > 1) {
            $streetSuburb['suburb'] = array_pop($addressArray);
            $streetSuburb['street'] = implode(chr(10), $addressArray);
        }

        return $streetSuburb;
    }

    /**
     * @return string $street
     */
    public function getStreet()
    {
        $streetSuburb = $this->getStreetSuburbArray();
        return $streetSuburb['street'];
    }

    /**
     * @return string $suburb
     */
    public function getSuburb()
    {
        $streetSuburb = $this->getStreetSuburbArray();
        return $streetSuburb['suburb'];
    }

}
