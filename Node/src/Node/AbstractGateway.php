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
     * Retrieve and action all updated records (either from polling, pushed data, or other sources).
     */
    abstract public function retrieve();

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