<?php
/**
 * Product entity
 * @category Magelink
 * @package Entity\Wrapper
 * @author Seo Yao
 * @author Andreas Gerhards <andreas@lero9.co.nz>
 * @copyright Copyright (c) 2014 LERO9 Ltd.
 * @license Commercial - All Rights Reserved
 */

namespace Entity\Wrapper;

use Entity\Entity;
use Log\Service\LogService;
use Magelink\Exception\MagelinkException;


class Product extends AbstractWrapper
{

    const TYPE_SIMPLE = 'simple';
    const TYPE_CONFIGURABLE = 'configurable';


    /**
     * Checks if product is shippable - dependend on Magento node or Magento2 node
     * @return bool
     */
    public function isShippable()
    {
        /** @var ServiceLocatorAwareInterface $magentoService */
        $magentoService = $this->getServiceLocator()->get('magentoService');
        if (!$magentoService) {
            $magentoService = $this->getServiceLocator()->get('magento2Service');
        }

        if (!$magentoService) {
            throw new MagelinkException('Neither Magento node nor Magento2 node installed.');
        }else{
            $isShippable = $magentoService->isProductTypeShippable($this->getData('type'));
        }

        return $isShippable;
    }

    /**
     * @return bool $isTypeConfigurable
     */
    public function isTypeConfigurable()
    {
        return $this->getData('type', NULL) == self::TYPE_CONFIGURABLE;
    }

    /**
     * @return bool $isTypeSimple
     */
    public function isTypeSimple()
    {
        return $this->getData('type', NULL) == self::TYPE_SIMPLE;
    }

    /**
     * @param $nodeId
     * @return array $configurableSimples
     */
    public function getConfigurableSimples($nodeId)
    {
        return $this->_entityService->loadAssociatedProducts($nodeId, $this);
    }

    /**
     * @param $nodeId
     * @return array $configurableProductLinks
     */
    public function getConfigurableProductLinks($nodeId)
    {
        $configurableProductLinks = array();

        /** @var Product $product */
        foreach ($this->getConfigurableSimples($nodeId) as $product) {
            $localId = $this->_entityService->getLocalId($nodeId, $product);
            if (is_null($localId)) {
                $this->getServiceLocator()->get('logService')->log(LogService::LEVEL_ERROR,
                    'ety_p_lid_err',
                    'Local id for product '.$product->getUniqueId().' on node '.$nodeId.' is missing.',
                    array('configurable'=>$this->getUniqueId(), 'simple'=>$product->getUniqueId(), 'node'=>$nodeId)
                );
            }else{
                $configurableProductLinks[] = $this->_entityService->getLocalId($nodeId, $product);
            }
        }

        return $configurableProductLinks;
    }

}
