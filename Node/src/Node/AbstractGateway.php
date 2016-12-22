<?php
/**
 * @category Node
 * @package Node
 * @author Andreas Gerhards <andreas@lero9.co.nz>
 * @copyright Copyright (c) 2014 LERO9 Ltd.
 * @license Commercial - All Rights Reserved
 */

namespace Node;

use Entity\Service\EntityService;
use Log\Service\LogService;
use Magelink\Exception\MagelinkException;
use Node\Service\NodeService;
use Zend\ServiceManager\ServiceLocatorAwareInterface;
use Zend\ServiceManager\ServiceLocatorInterface;


abstract class AbstractGateway implements ServiceLocatorAwareInterface
{
    const GATEWAY_NODE_CODE = 'nod';
    const GATEWAY_ENTITY_CODE = 'act';
    const GATEWAY_ENTITY = 'abstract';

    /** @var \Node\AbstractNode $this->_node */
    protected $_node;
    /** @var \Node\Entity\Node $this->_nodeEntity */
    protected $_nodeEntity;

    /** @var bool $this->isOverdueRun */
    protected $isOverdueRun = NULL;

    /** @var ServiceLocatorAwareInterface $this->_serviceLocator */
    protected $_serviceLocator;
    /** @var NodeService $this->_nodeService */
    protected $_nodeService;
    /** @var EntityService $this->_entityService */
    protected $_entityService;

    /** @var int $this->apiOverlappingSeconds */
    protected $apiOverlappingSeconds = 3;
    /** @var int $this->lastSinceId */
    protected $lastSinceId = NULL;
    /** @var int $this->lastRetrieveTimestamp */
    protected $lastRetrieveTimestamp = NULL;
    /** @var int $this->retrieveTimestamp */
    protected $retrieveTimestamp = NULL;
    /** @var int $this->newRetrieveTimestamp */
    protected $newRetrieveTimestamp = NULL;


    /**
     * @param ServiceLocatorInterface $serviceLocator
     */
    public function setServiceLocator(ServiceLocatorInterface $serviceLocator)
    {
        $this->_serviceLocator = $serviceLocator;
    }

    /**
     * @return ServiceLocatorInterface
     */
    public function getServiceLocator()
    {
        return $this->_serviceLocator;
    }

    /**
     * Initialize the gateway and perform any setup actions required.
     * @param AbstractNode $node
     * @param Entity\Node $nodeEntity
     * @param string $entityType
     * @return boolean
     */
    public function init(AbstractNode $node, Entity\Node $nodeEntity, $entityType, $isOverdueRun)
    {
        $namespace = strtok(get_called_class(), '\\');
        $allowedNodeClass = $namespace.'\Node';
        $allowedNode = new $allowedNodeClass();

        if (!($node instanceof $allowedNode)) {
            throw new MagelinkException('Invalid node type '.get_class($this->_node).' for '.$namespace.' gateways');
            $success = FALSE;
        }else{
            $this->_node = $node;
            $this->_nodeEntity = $nodeEntity;
            $this->isOverdueRun = $isOverdueRun;

            $this->_nodeService = $this->getServiceLocator()->get('nodeService');
            $this->_entityService = $this->getServiceLocator()->get('entityService');
            //$this->_entityConfigService = $this->getServiceLocator()->get('entityConfigService');

            $success = $this->_init($entityType);
        }

        return $success;
    }

    /**
     * Initialize the gateway and perform any setup actions required. (module implementation)
     * @param $entityType
     * @return bool $success
     */
    abstract protected function _init($entityType);

    /**
     * @param string $mapType
     * @param mixed $key
     * @param bool $flip
     * @return string|NULL $string
     * @throws MagelinkException
     */
    protected static function getMappedString($mapType, $key, $flip = FALSE)
    {
        $string = NULL;

        if (!is_null($key)) {
            $mapName = strtolower($mapType).'ById';

            if (isset(static::$$mapName)) {
                $map = static::$$mapName;
            }else{
                $map = array();
            }

            $isValid = count($map) > 0;
            $message = 'static::$'.$mapName.'['.var_export($key, TRUE).']';

            if ($isValid) {
                if ($flip) {
                    $message = 'Flipped '.$message;
                    $duplicate = FALSE;

                    foreach ($map as $mapKey=>$mapValue) {
                        if ($key == $mapValue || is_array($mapValue) && in_array($key, $mapValue)) {
                            if (is_null($string) && !$duplicate) {
                                $string = $mapKey;
                            }elseif (!is_null($string)) {
                                $string = NULL;
                                $duplicate = TRUE;
                            }
                        }
                    }
                }elseif (isset($map[$key])) {
                    $string = $map[$key];
                }

                if (!isset($string)) {
                    $message .= ' is not set or existing on '.get_called_class().'.';
                    throw new MagelinkException($message);
                }
            }else{
                $message = 'self::$'.$mapName.' is not valid.';
                throw new MagelinkException($message);
            }
        }

        return $string;
    }

    /**
     * @param string $mapType
     * @param int $string
     * @return mixed|FALSE|NULL $id
     */
    protected static function getMappedId($mapType, $string)
    {
        return self::getMappedString($mapType, $string, TRUE);
    }

/*
    protected static function map() {}
*/

    /**
     * @return string $logCode
     */
    protected function getLogCode()
    {
        return static::GATEWAY_NODE_CODE.'_'.static::GATEWAY_ENTITY_CODE;
    }

    /**
     * @return int $this->newRetrieveTimestamp
     */
    protected function getRetrieveTimestamp()
    {
        if ($this->retrieveTimestamp === NULL) {
            $this->retrieveTimestamp = time();
        }

        return $this->retrieveTimestamp;
    }

    /**
     * @return int $adjustedTimestamp
     */
    protected function getAdjustedTimestamp($timestamp = NULL)
    {
        if (is_null($timestamp) || intval($timestamp) != $timestamp || $timestamp == 0) {
            $timestamp = time();
        }

        return $timestamp - $this->apiOverlappingSeconds;
    }

    /**
     * @return int $this->newRetrieveTimestamp
     */
    protected function getNewRetrieveTimestamp()
    {
        if ($this->newRetrieveTimestamp === NULL) {
            $this->newRetrieveTimestamp = $this->getAdjustedTimestamp($this->getRetrieveTimestamp());
        }

        return $this->newRetrieveTimestamp;
    }

    /**
     * @return string $this->lastSinceId
     */
    protected function getLastSinceId()
    {
        if (is_null($this->lastSinceId)) {
            $this->lastSinceId = max(1,
                $this->_nodeService->getSinceId($this->_nodeEntity->getNodeId(), static::GATEWAY_ENTITY, 'retrieve')
            );
        }

        return $this->lastSinceId;
    }

    /**
     * @return int $this->lastRetrieveTimestamp
     */
    protected function getLastRetrieveTimestamp()
    {
        if ($this->lastRetrieveTimestamp === NULL) {
            $this->lastRetrieveTimestamp =
                $this->_nodeService->getTimestamp($this->_nodeEntity->getNodeId(), static::GATEWAY_ENTITY, 'retrieve');
        }

        return $this->lastRetrieveTimestamp;
    }

    /** @param int $timestamp
     * @return string $date
     */
    protected function convertTimestampToExternalDateFormat($timestamp)
    {
        $deltaInSeconds = intval($this->_node->getConfig('time_delta_'.static::GATEWAY_ENTITY)) * 3600;
        $date = date('Y-m-d H:i:s', $timestamp + $deltaInSeconds);

        return $date;
    }

    /**
     * @return int $this->lastRetrieveTimestamp
     */
    protected function getLastRetrieveDate()
    {
        $lastRetrieve = $this->convertTimestampToExternalDateFormat($this->getLastRetrieveTimestamp());
        return $lastRetrieve;
    }

    /**
     * Frame method for retrieval
     */
    public function retrieve()
    {
        $this->getNewRetrieveTimestamp();
        $this->getLastRetrieveDate();

        $results = $this->retrieveEntities();

        if ($results > 0) {
            $this->_nodeService->setTimestamp($this->_nodeEntity->getNodeId(),
                static::GATEWAY_ENTITY, 'retrieve', $this->getNewRetrieveTimestamp());
        }

        $logCode = static::GATEWAY_NODE_CODE.'_'.static::GATEWAY_ENTITY_CODE.'_re_no';
        $seconds = ceil($this->getAdjustedTimestamp() - $this->getNewRetrieveTimestamp());
        $message = 'Retrieved '.$results.' '.static::GATEWAY_ENTITY.'s in '.$seconds.'s up to '
            .strftime('%H:%M:%S, %d/%m', $this->retrieveTimestamp).'.';
        $logData = array('type'=>static::GATEWAY_ENTITY, 'amount'=>$results, 'period [s]'=>$seconds);
        if ($results > 0) {
            $logData['per entity [s]'] = round($seconds / $results, 3);
        }
        $this->getServiceLocator()->get('logService')->log(LogService::LEVEL_INFO, $logCode, $message, $logData);
    }

    /**
     * Retrieve and action all updated records (either from polling, pushed data, or other sources).
     * @return int $results
     */
    abstract protected function retrieveEntities();

    /**
     * Write out all the updates to the given entity.
     * @param \Entity\Entity $entity
     * @param string[] $attributes
     * @param int $type
     * @return bool|NULL
     */
    abstract public function writeUpdates(\Entity\Entity $entity, $attributes, $type = \Entity\Update::TYPE_UPDATE);

    /**
     * Write out the given action.
     * @param \Entity\Action $action
     * @return bool Whether to mark the action as complete
     */
    abstract public function writeAction(\Entity\Action $action);

}
