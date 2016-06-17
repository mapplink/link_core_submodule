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
use Magelink\Exception\MagelinkException;
use Node\Service\NodeService;
use Zend\ServiceManager\ServiceLocatorAwareInterface;
use Zend\ServiceManager\ServiceLocatorInterface;


abstract class AbstractGateway implements ServiceLocatorAwareInterface
{
    /** @var \Magento\Node */
    protected $_node;
    /** @var \Node\Entity\Node $_nodeEntity */
    protected $_nodeEntity;

    /** @var bool $isOverdueRun */
    protected $isOverdueRun = NULL;

    /** @var ServiceLocatorAwareInterface $_serviceLocator */
    protected $_serviceLocator;
    /** @var NodeService $_nodeService */
    protected $_nodeService;
    /** @var EntityService $_entityService */
    protected $_entityService;

    /** @var int $retrieveTimestamp */
    protected $retrieveTimestamp = NULL;


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
     * Frame method for retrieval
     */
    public function retrieve()
    {
        $this->getNewRetrieveTimestamp();
        $this->getLastRetrieveDate();

        $results = $this->retrieveEntities();

        $logCode = static::GATEWAY_NODE_CODE.'_'.static::GATEWAY_ENTITY_CODE.'_re_no'
        $seconds = ceil($this->getAdjustedTimestamp() - $this->getNewRetrieveTimestamp());
        $message = 'Retrieved '.$results.' '.static::GATEWAY_ENTITY.'s in '.$seconds.'s up to '
            .strftime('%H:%M:%S, %d/%m', $this->retrieveTimestamp).'.';
        $logData = array('type'=>static::GATEWAY_ENTITY, 'amount'=>$results, 'period [s]'=>$seconds);
        if (count($results) > 0) {
            $logData['per entity [s]'] = round($seconds / count($results), 3);
        }
        $this->getServiceLocator()->get('logService')->log(LogService::LEVEL_INFO, 'mag_cu_re_no', $message, $logData);
    }

    /**
     * Retrieve and action all updated records (either from polling, pushed data, or other sources).
     * @return array $retrieveResults
     */
    abstract protected function retrieveEntities();

    /**
     * Write out all the updates to the given entity.
     * @param \Entity\Entity $entity
     * @param string[] $attributes
     * @param int $type
     */
    abstract public function writeUpdates(\Entity\Entity $entity, $attributes, $type = \Entity\Update::TYPE_UPDATE);

    /**
     * Write out the given action.
     * @param \Entity\Action $action
     * @return bool Whether to mark the action as complete
     */
    abstract public function writeAction(\Entity\Action $action);

}
