<?php
/**
 * Node\Abstract Node
 *
 * @category Node
 * @package Node
 * @author Matt Johnston
 * @author Andreas Gerhards <andreas@lero9.co.nz>
 * @copyright Copyright (c) 2014 LERO9 Ltd.
 * @license Commercial - All Rights Reserved
 */

namespace Node;

use Log\Service\LogService;
use Magelink\Exception\MagelinkException;
use Magelink\Exception\NodeException;
use Magelink\Exception\GatewayException;
use Node\Entity\Node as NodeEntity;
use Node\Service\NodeService;
use Zend\ServiceManager\ServiceLocatorAwareInterface;
use Zend\ServiceManager\ServiceLocatorInterface;


abstract class AbstractNode implements ServiceLocatorAwareInterface
{

    /** @var \Node\AbstractGateway[] $_gateway */
    protected $_gateway = array();

    /** @var NodeEntity $_entity */
    protected $_entity = NULL;

    /** @var array $_config */
    protected $_config = NULL;

    /** @var array $_typeConfig */
    protected $_typeConfig = NULL;

    /** @var array $updates */
    protected $updates = array();

    /** @var array $actions */
    protected $actions = array();

    /** @var NodeService $_nodeService */
    protected $_nodeService = NULL;

    /** @var ServiceLocatorInterface $_serviceLocator */
    protected $_serviceLocator;


    /**
     * Set service locator
     * @param ServiceLocatorInterface $serviceLocator
     */
    public function setServiceLocator(ServiceLocatorInterface $serviceLocator)
    {
        $this->_serviceLocator = $serviceLocator;
    }

    /**
     * Get service locator
     * @return ServiceLocatorInterface
     */
    public function getServiceLocator()
    {
        return $this->_serviceLocator;
    }

    /**
     * Sets up internal data structures, calls local _init method, and creates appropriate gateways.
     * @param NodeEntity $nodeEntity
     * @see _init()
     */
    public function init(NodeEntity $nodeEntity)
    {
        $this->_entity = $nodeEntity;
        $nodeEntity->loadSimpleData();
        $this->_config = $nodeEntity->getSimpleData();

        $this->getServiceLocator()->get('logService')->log(LogService::LEVEL_INFO, 'node_init', 'AbstractNode init',
            array('node'=>get_class($this), 'id'=>$nodeEntity->getNodeId()), array('node'=>$this));

        $appConfig = $this->getServiceLocator()->get('Config');
        $this->_typeConfig = $appConfig['node_types'][$this->_entity->getType()];

        $this->_init($nodeEntity);
    }

    /**
     * @return int $this->_entity->getId()
     */
    public function getNodeId()
    {
        return $this->_entity->getId();
    }

    /**
     * @return string $this->_entity->getName()
     */
    public function getTitle(){
        return $this->_entity->getName();
    }

    /**
     * @see _deinit()
     */
    public function deinit()
    {
        $this->_deinit();
    }

    /**
     * Lazy-load a gateway for the provided entity type (providing DI and initialization)
     * @param $entityType
     * @return AbstractGateway
     */
    protected function _lazyLoad($entityType)
    {
        $gateway = $this->_createGateway($entityType);
        if ($gateway instanceof ServiceLocatorAwareInterface) {
            $gateway->setServiceLocator($this->getServiceLocator());
        }

        if ($gateway) {
            $gateway->init($this, $this->_entity, $entityType);
        }

        return $gateway;
    }

    /**
     * Retrieves all data from the node’s source - calls the appropriate retrieve functions on the gateways
     *   as determined by the optional parameter, or if not specified, the router edges and config.
     * @param string[] $gateways
     */
    public function retrieve($gateways = NULL)
    {
        if ($gateways == NULL){
            $gateways = $this->_typeConfig['entity_type_support'];
        }

        $this->getServiceLocator()->get('logService')->log(LogService::LEVEL_INFO, 'node_retrieve',
            'AbstractNode retrieve', array('gateways'=>$gateways), array('node'=>$this));

        foreach ($gateways as $gateway) {
            if(!isset($this->_gateway[$gateway])){
                // Lazy-load gateway for entity type
                $this->_gateway[$gateway] = $this->_lazyLoad($gateway);
            }
            if ($this->_gateway[$gateway]) {
                try{
                    $this->_gateway[$gateway]->retrieve();
                }catch (GatewayException $gatewayException) {
                    $logMessage = 'Uncaught exception while processing node '.$this->getNodeId().': '
                        .$gatewayException->getMessage();
                    $this->getServiceLocator()->get('logService')
                        ->log(LogService::LEVEL_ERROR, 'gatewayex', $logMessage,
                            array($gatewayException->getMessage(), $gatewayException->getTraceAsString()),
                            array('exception'=>$gatewayException, 'node'=>$this->getNodeId())
                        );
                    print PHP_EOL.$gatewayException->getTraceAsString().PHP_EOL;
                }
            }
        }
    }

    /**
     * @return array $this->actions
     */
    protected function getPendingActions()
    {
        if (!$this->actions) {
            $this->actions = $this->getServiceLocator()->get('nodeService')->getPendingActions($this->_entity);
        }

        return $this->actions;
    }

    /**
     * @return array $this->updates
     */
    protected function getPendingUpdates()
    {
        if (!$this->updates) {
            $this->updates = $this->getServiceLocator()->get('nodeService')->getPendingUpdates($this->_entity);
        }

        return $this->updates;
    }

    /**
     * @throws NodeException
     */
    protected function processActions()
    {
        $actions = $this->getPendingActions();

        foreach ($actions as $action) {
            /* @var $action \Entity\Action */
            $entityType = $action->getEntity()->getTypeStr();
            if (!isset($this->_gateway[$entityType])) {
                // Lazy-load gateway for entity type
                $this->_gateway[$entityType] = $this->_lazyLoad($entityType);
            }
            try{
                $result = true;
                if($this->_gateway[$entityType]){
                    $this->getServiceLocator()->get('logService')->log(LogService::LEVEL_INFO,
                        'send_action',
                        'Sending action '.$action->getId().' to '.$this->getNodeId().' ('.$action->getEntity()->getUniqueId().')',
                        array($action->getId()),
                        array('entity'=>$action->getEntity(), 'node'=>$this)
                    );
                    $result = $this->_gateway[$entityType]->writeAction($action);
                }
                if($result){
                    $this->_nodeService->setActionStatus($this->_entity, $action, 1);
                }
            }catch(MagelinkException $exception){
                $message = 'Uncaught exception during action processing for '.$action->getId()
                    .' to '.$this->getNodeId().': '.$exception->getMessage();
                $this->getServiceLocator()->get('logService')->log(LogService::LEVEL_ERROR,
                    'action_ex',
                    $message,
                    array($exception->getMessage(), $exception->getTraceAsString()),
                    array('exception'=>$exception)
                );
                throw new NodeException('Error applying actions: '.$exception->getMessage(), 0, $exception);
            }
        }
    }

    /**
     * @throws NodeException
     */
    protected function processUpdates()
    {
        $updatesByType = array();
        $updates = $this->getPendingUpdates();

        foreach ($updates as $update) {
            /* @var $update \Entity\Update */
            $entity = $update->getEntity();

            $entityType = $entity->getTypeStr();
            $entityId = $entity->getId();

            if (!isset($updatesByType[$entityType])) {
                $updatesByType[$entityType] = array($entityId=>array($update));
            }elseif (!isset($updatesByType[$entityType][$entityId])) {
                $updatesByType[$entityType][$entityId] = array($update);
            }else{
                $updatesByType[$entityType][$entityId][] = $update;
            }
        }

        foreach ($updatesByType as $entityType=>$entityTypeUpdates) {

            if (!isset($this->_gateway[$entityType])) {
                $this->_gateway[$entityType] = $this->_lazyLoad($entityType);
            }

            $attributes = $this->_nodeService->getSubscribedAttributeCodes($this->getNodeId(), $entityType);

            $updates = array();
            if ($this->_gateway[$entityType]) {
                // Combine all updates for one entity into a single update
                foreach ($entityTypeUpdates as $entityId=>$updatesPerEntityId) {
                    $update = $updatesPerEntityId[0];
                    $updates[$entityId] = array(
                        'entity'=>$update->getEntity(),
                        'attributes'=>array(),
                        'type'=>$update->getType(),
                        'combined'=>array()
                    );
                    foreach ($updatesPerEntityId as $update) {
                        $this->getServiceLocator()->get('logService')->log(LogService::LEVEL_INFO,
                                'comb_update',
                                'Combining updates '.$update->getLogId().' to '.$this->getNodeId(),
                                array('attributes'=>$update->getAttributesSimple()),
                                array('entity'=>$update->getEntity(), 'node'=>$this)
                            );

                        $affectedAttributes = array_intersect($update->getAttributesSimple(), $attributes);
                        $updates[$entityId]['attributes'] =
                            array_merge($affectedAttributes, $updates[$entityId]['attributes']);
                        $updates[$entityId]['type'] = max($updates[$entityId]['type'], $update->getType());
                        $updates[$entityId]['combined'][] = $update->getLogId();
                    }
                }
            }

            foreach ($updates as $entityId=>$update) {
                $logMessage = $entityId.' to '.$this->getNodeId();
                $logData =  array(
                    'entity id'=>$entityId,
                    'entity type'=>$update['type'],
                    'attributes'=>$update['attributes'],
                    'combined'=>$update['combined']
                );

                $this->getServiceLocator()->get('logService')
                    ->log(LogService::LEVEL_INFO, 'push_update', 'Pushing update for '.$logMessage, $logData,
                        array('entity'=>$entityId, 'node'=>$this));

                try{
                    $this->_gateway[$entityType]->writeUpdates(
                        $update['entity'],
                        $update['attributes'],
                        $update['type']
                    );
                }catch (GatewayException $gatewayException) {
                    $logMessage = 'Exception during update processing for '.$logMessage.': '.$gatewayException->getMessage();
                    $logData = array_merge($logData, array(
                        'exception message'=>$gatewayException->getMessage(),
                        'exception trace'=>$gatewayException->getTraceAsString()
                    ));
                    $this->getServiceLocator()->get('logService')
                        ->log(LogService::LEVEL_ERROR, 'update_ex_gw', $logMessage, $logData,
                            array('update'=>$update, 'exception'=>$gatewayException));
                    unset($this->_gateway[$entityType]);
                    break;
                }catch (MagelinkException $exception) {
                    $logMessage = 'Exception during update processing for '.$logMessage.': '.$exception->getMessage();
                    $logData = array_merge($logData, array(
                        'exception message'=>$exception->getMessage(),
                        'exception trace'=>$exception->getTraceAsString()
                    ));
                    $this->getServiceLocator()->get('logService')
                        ->log(LogService::LEVEL_ERROR, 'update_ex_ml', $logMessage, $logData,
                            array('update'=>$update, 'exception'=>$exception));

                    throw new NodeException($logMessage, 0, $exception);
                    break;
                }

                foreach ($entityTypeUpdates[$entityId] as $updateToBeMarkedAsCompleted) {
                    $this->_nodeService->setUpdateStatus($this->_entity, $updateToBeMarkedAsCompleted, 1);
                }
            }
        }
    }

    /**
     * Updates all data into the node’s source - should load and collapse all pending updates and call writeUpdates,
     *   as well as loading and sequencing all actions.
     * @throws NodeException
     */
    public function update()
    {
        $this->_nodeService = $this->getServiceLocator()->get('nodeService');
        /** @var LogService $logService */
        $logService = $this->getServiceLocator()->get('logService');

        $this->actions = $this->getPendingActions();
        $this->updates = $this->getPendingUpdates();

        $nodeClass = get_called_class();
        if (strpos($nodeClass, 'Node') === 0) {
            $logCode = '';
        }else{
            $logCode = strtolower(substr($nodeClass, 0, 3)).'_';
        }
        $logCode .= 'node_upd';
        $logMessage = $nodeClass.' update: '.count($this->updates).' updates, '.count($this->actions).' actions.';
        $logData = array('class'=>$nodeClass, 'updates'=>count($this->updates), 'actions'=>count($this->actions));
        $logEntities = array('node'=>$this, 'actions'=>$this->actions, 'updates'=>$this->updates);

        $logService->log(LogService::LEVEL_INFO,$logCode, $logMessage, $logData, $logEntities);

        $startTimestamp = microtime(TRUE);
        $this->processUpdates();

        $logMessage = $nodeClass.'->processUpdates() took '.round(microtime(TRUE) - $startTimestamp, 1).'s.';
        $logData = array('message'=>$logMessage);
        $logService->log(LogService::LEVEL_DEBUGINTERNAL, $logCode.'_pup', $logMessage, $logData);

        $startTimestamp = microtime(TRUE);
        $this->processActions();

        $logMessage = $nodeClass.'->processActions() took '.round(microtime(TRUE) - $startTimestamp, 1).'s.';
        $logData = array('message'=>$logMessage);
        $logService->log(LogService::LEVEL_DEBUGINTERNAL, $logCode.'_pac', $logMessage, $logData);
    }

    /**
     * Returns the value of a config setting for this node, or if no key specified, all keys
     * @param string|null $key
     * @return null
     */
    public function getConfig($key = NULL)
    {
        if ($key == NULL) {
            $config = $this->_config;
        }elseif (isset($this->_config[$key])) {
            $config = $this->_config[$key];
        }else{
            $config = NULL;
        }

        return $config;
    }

    /**
     * Implemented in each NodeModule
     * Should set up any initial data structures, connections, and open any required files that the node needs to operate.
     * In the case of any errors that mean a successful sync is unlikely, a Magelink\Exception\InitException MUST be thrown.
     *
     * @param NodeEntity $nodeEntity
     */
    protected abstract function _init(NodeEntity $nodeEntity);

    /**
     * Implemented in each NodeModule
     * The opposite of _init - close off any connections / files / etc that were opened at the beginning.
     * This will always be the last call to the Node.
     * NOTE: This will be called even if the Node has thrown a NodeException, but NOT if a SyncException
     *   or other Exception is thrown (which represents an irrecoverable error)
     */
    protected abstract function _deinit();

    /**
     * Implemented in each NodeModule
     * Returns an instance of a subclass of AbstractGateway that can handle the provided entity type.
     *
     * @param string $entity_type
     * @return AbstractGateway
     */
    protected abstract function _createGateway($entity_type);

}