<?php
/**
 * Node\Abstract Node
 * @category Node
 * @package Node
 * @author Matt Johnston
 * @author Andreas Gerhards <andreas@lero9.co.nz>
 * @copyright Copyright (c) 2014 LERO9 Ltd.
 * @license Commercial - All Rights Reserved
 */

namespace Node;

use Entity\Action;
use Entity\Update;
use Entity\Service\EntityService;
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
    /** @var ServiceLocatorAwareInterface[] $_api */
    protected $_api = array();

    /** @var NodeEntity $_entity */
    protected $_entity = NULL;

    /** @var array $_config */
    protected $_config = NULL;
    /** @var array $_typeConfig */
    protected $_typeConfig = NULL;

    /** @var bool $isOverdueRun */
    protected $isOverdueRun = NULL;
    /** @var Update[] $updates */
    protected $updates = array();
    /** @var Action[] $actions */
    protected $actions = array();

    /** @var EntityService $_entityService */
    protected $_entityService = NULL;
    /** @var LogService $_logService */
    protected $_logService = NULL;
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
     * @param bool $isOverdueRun
     * @throws MagelinkException
     * @see _init()
     */
    public function init(NodeEntity $nodeEntity, $isScheduledRun = TRUE)
    {
        $this->_entity = $nodeEntity;
        $nodeEntity->loadSimpleData();
        $this->isOverdueRun = !$isScheduledRun;

        $this->_config = $nodeEntity->getSimpleData();

        $appConfig = $this->getServiceLocator()->get('Config');
        $this->_typeConfig = $appConfig['node_types'][$this->_entity->getType()];

        $this->_entityService = $this->getServiceLocator()->get('_entityService');
        $this->_logService = $this->getServiceLocator()->get('_logService');
        $this->_nodeService = $this->getServiceLocator()->get('_nodeService');

        $this->_logService->log(LogService::LEVEL_INFO,
            $this->getNodeLogPrefix().'init',
            'AbstractNode init',
            array('node'=>get_class($this), 'id'=>$nodeEntity->getNodeId()),
            array('node'=>$this)
        );

        $this->_init();
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
    public function getTitle()
    {
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
            $gateway->init($this, $this->_entity, $entityType, $this->isOverdueRun);
        }

        return $gateway;
    }

    /**
     * @return string $nodeLogPrefix
     */
    abstract protected function getNodeLogPrefix();

    /**
     * Retrieves all data from the node’s source - calls the appropriate retrieve functions on the gateways
     *   as determined by the optional parameter, or if not specified, the router edges and config.
     * @param string[] $gateways
     */
    public function retrieve($gateways = NULL)
    {
        $this->getServiceLocator()->get('logService')
            ->log(LogService::LEVEL_INFO, $this->getNodeLogPrefix().'retrieve',
                'Node retrieve starts', array(), array('node'=>$this));

        if ($gateways == NULL) {
            $gateways = $this->_typeConfig['entity_type_support'];
        }

        $this->_logService->log(LogService::LEVEL_INFO, $this->getNodeLogPrefix().'retrieve',
            'AbstractNode retrieve', array('gateways'=>$gateways), array('node'=>$this));

        foreach ($gateways as $gateway) {
            if (!isset($this->_gateway[$gateway])) {
                // Lazy-load gateway for entity type
                $this->_gateway[$gateway] = $this->_lazyLoad($gateway);
            }
            if ($this->_gateway[$gateway]) {
                try{
                    $this->_gateway[$gateway]->retrieve();
                }catch (GatewayException $gatewayException) {
                    $logMessage = 'Uncaught exception while processing node '.$this->getNodeId().': '
                        .$gatewayException->getMessage();
                    $this->_logService->log(LogService::LEVEL_ERROR,
                        $this->getNodeLogPrefix().'gatewayex',
                        $logMessage,
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
            $this->actions = $this->_nodeService->getPendingActions($this->_entity);
        }

        return $this->actions;
    }

    /**
     * @return array $this->updates
     */
    protected function getPendingUpdates()
    {
        if (!$this->updates) {
            $this->updates = $this->_nodeService->getPendingUpdates($this->_entity);
        }

        return $this->updates;
    }

    /**
     * @throws NodeException
     */
    protected function processActions()
    {
        $startMethod = microtime(TRUE);
        $logCode = $this->getNodeLogPrefix().'procact';
        $logMessage = '->processActions() started at '.date('d/m H:i:s', $startMethod).'.';
        $this->_logService->log(LogService::LEVEL_INFO, $logCode, $logMessage, array());

        $actions = $this->getPendingActions();

        foreach ($actions as $action) {
            /* @var $action \Entity\Action */
            $entityType = $action->getEntity()->getTypeStr();
            if (!isset($this->_gateway[$entityType])) {
                // Lazy-load gateway for entity type
                $this->_gateway[$entityType] = $this->_lazyLoad($entityType);
            }
            try{
                $success = TRUE;
                if ($this->_gateway[$entityType]) {
                    $this->_logService->log(LogService::LEVEL_INFO,
                        $this->getNodeLogPrefix().'send_action',
                        'Sending action '.$action->getId().' to '.$this->getNodeId().' ('.$action->getEntity()->getUniqueId().')',
                        array($action->getId()),
                        array('entity'=>$action->getEntity(), 'node'=>$this)
                    );
                    $success = $this->_gateway[$entityType]->writeAction($action);
                }
                if ($success !== FALSE) {
                    $this->_nodeService->setActionStatus($this->_entity, $action, 1);
                }
            }catch (MagelinkException $exception) {
                $message = 'Uncaught exception during action processing for '.$action->getId()
                    .' to '.$this->getNodeId().': '.$exception->getMessage();
                $this->_logService->log(LogService::LEVEL_ERROR,
                    $this->getNodeLogPrefix().'action_ex',
                    $message,
                    array($exception->getMessage(), $exception->getTraceAsString()),
                    array('exception'=>$exception)
                );
                throw new NodeException('Error applying actions: '.$exception->getMessage(), 0, $exception);
            }
        }

        $logMessage = '->processActions() took '.round(microtime(TRUE) - $startMethod, 1).'s.';
        $this->_logService->log(LogService::LEVEL_DEBUGINTERNAL, $logCode.'_rt', $logMessage, array());
    }

    /**
     * @throws NodeException
     */
    protected function processUpdates()
    {
        $startMethod = microtime(TRUE);
        $logCode = $this->getNodeLogPrefix().'procupd';
        $logMessage = '->processUpdates() started at '.date('d/m H:i:s', $startMethod).'.';
        $this->_logService->log(LogService::LEVEL_INFO, $logCode, $logMessage, array('updates'=>count($this->updates)));

        $updatesByType = array();
        $this->getPendingUpdates();


        /* @var \Entity\Update $update */
        foreach ($this->updates as $update) {
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
        $endUpdatesLoop = $start = microtime(TRUE);

        $triggerSliFeed = FALSE;
        $markedAsCompleted = array();
        $createUpdateData = $writeUpdates = 0;

        /** @var \Entity\Update[] $entityTypeUpdates */
        foreach ($updatesByType as $entityType=>$entityTypeUpdates) {

            if (!isset($this->_gateway[$entityType])) {
                $this->_gateway[$entityType] = $this->_lazyLoad($entityType);
            }

            $attributes = $this->_nodeService->getSubscribedAttributeCodes($this->getNodeId(), $entityType);

            $updates = array();
            if ($this->_gateway[$entityType]) {
                /** Combine all updates for one entity into a single update
                 * @var \Entity\Update $updatesPerEntityId */
                foreach ($entityTypeUpdates as $entityId=>$updatesPerEntityId) {
                    $update = $updatesPerEntityId[0];
                    $updates[$entityId] = array(
                        'entity'=>$update->getEntity(),
                        'attributes'=>array(),
                        'type'=>$update->getType(),
                        'combined'=>array()
                    );
                    foreach ($updatesPerEntityId as $update) {
                        $this->_logService->log(LogService::LEVEL_DEBUG,
                            $this->getNodeLogPrefix().'comb_update',
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

                if ($entityType == 'product' || $entityType == 'stockitem') {
                    $triggerSliFeed = TRUE;
                }
            }

            $createUpdateData += -$start + ($start = microtime(TRUE));

            foreach ($updates as $entityId=>$update) {
                $logMessage = $entityId.' to '.$this->getNodeId();
                $logData =  array(
                    'entity id'=>$entityId,
                    'entity type'=>$update['type'],
                    'attributes'=>array_unique($update['attributes']),
                    'combined'=>$update['combined']
                );

                $this->_logService->log(LogService::LEVEL_INFO,
                    $this->getNodeLogPrefix().'push_update',
                    'Pushing update for '.$logMessage,
                    $logData,
                    array('entity'=>$entityId, 'node'=>$this)
                );

                try{
                    $success = $this->_gateway[$entityType]->writeUpdates(
                        $update['entity'],
                        $update['attributes'],
                        $update['type']
                    );

                    if ($success === FALSE) {
                        $entityTypeUpdates[$entityId] = array();
                    }
                }catch (GatewayException $gatewayException) {
                    $logMessage = 'Exception during update processing for '.$logMessage.': '.$gatewayException->getMessage();
                    $logData = array_merge($logData, array(
                        'exception message'=>$gatewayException->getMessage(),
                        'exception trace'=>$gatewayException->getTraceAsString()
                    ));
                    $this->_logService->log(LogService::LEVEL_ERROR,
                        $this->getNodeLogPrefix().'upd_ex_gw',
                        $logMessage,
                        $logData,
                        array('update'=>$update, 'exception'=>$gatewayException)
                    );
                    unset($this->_gateway[$entityType]);
                    break;
                }catch (MagelinkException $exception) {
                    $logMessage = 'Exception during update processing for '.$logMessage.': '.$exception->getMessage();
                    $logData = array_merge($logData, array(
                        'exception message'=>$exception->getMessage(),
                        'exception trace'=>$exception->getTraceAsString()
                    ));
                    $this->_logService->log(LogService::LEVEL_ERROR,
                        $this->getNodeLogPrefix().'upd_ex_ml',
                        $logMessage,
                        $logData,
                        array('update'=>$update, 'exception'=>$exception)
                    );

                    throw new NodeException($logMessage, 0, $exception);
                    break;
                }

                /** @var \Entity\Update $updateToBeMarkedAsCompleted */
                foreach ($entityTypeUpdates[$entityId] as $updateToBeMarkedAsCompleted) {
                    $markedAsCompleted[] = $updateToBeMarkedAsCompleted->getLogId();
                    $this->_nodeService->setUpdateStatus($this->_entity, $updateToBeMarkedAsCompleted, 1);
                }
            }

            $writeUpdates += -$start + ($start = microtime(TRUE));
        }
        $endUpdatesByTypeLoop = microtime(TRUE);

        $logMessage = '->processUpdates() took '.round($endUpdatesByTypeLoop - $startMethod, 1).'s.'
            .' Updates loop took '.round($endUpdatesLoop - $startMethod, 1).'s ('.count($this->updates).').'
            .' UpdatesByType took '.round($endUpdatesByTypeLoop - $endUpdatesLoop, 1).'s ('.count($updatesByType).'),'
            .' '.round($createUpdateData, 1).'s spend on preparing data, '.round($writeUpdates, 1).'s on writing.'
            .' '.count($markedAsCompleted).' updates marked as completed.';
        $logData = array('marked as completed'=>implode(', ', $markedAsCompleted));
        $this->_logService->log(LogService::LEVEL_INFO, $logCode.'_rt', $logMessage, $logData);

        return $triggerSliFeed;
    }

    /**
     * @param string $nodeClass
     * @param bool $isMethodEnd
     * @return string $logCode
     */
    protected function logTimes($nodeClass, $isMethodEnd = FALSE)
    {
        $currentTime = microtime(TRUE);

        $logCode = $this->getNodeLogPrefix().'upd';
        $logData = array('class'=>$nodeClass);
        if (!$isMethodEnd) {
            $logMessage = $nodeClass.' update started at '.date('d/m H:i:s', $currentTime).'.';
            $this->methodStartTime = $currentTime;
        }else{
            $runtime = round($currentTime - $this->methodStartTime, 1);
            $logCode .= '_end';
            $logMessage = $nodeClass.' update finished at '.date('d/m H:i:s', $currentTime).'. Runtime: '.$runtime.'s.';
            $logData['runtime'] = $runtime;
            $this->methodStartTime = NULL;
        }
        $this->_logService->log(LogService::LEVEL_INFO, $logCode, $logMessage, $logData);

        return $logCode;
    }

    /**
     * Updates all data into the node’s source - should load and collapse all pending updates and call writeUpdates,
     *   as well as loading and sequencing all actions.
     * @throws NodeException
     */
    public function update()
    {
        $this->getServiceLocator()->get('logService')->log(LogService::LEVEL_INFO,
            $this->getNodeLogPrefix().'update', 'Node update starts', array(), array('node'=>$this));

        $nodeClass = get_called_class();
        $logCode = $this->logTimes($nodeClass);

        $this->getPendingUpdates();
        $this->getPendingActions();

        $logMessage = $nodeClass.' update: '.count($this->updates).' updates, '.count($this->actions).' actions.';
        $logData = array('updates'=>count($this->updates), 'actions'=>count($this->actions));
        $logEntities = array('node'=>$this, 'actions'=>$this->actions, 'updates'=>$this->updates);
        $this->_logService->log(LogService::LEVEL_INFO, $logCode.'_no', $logMessage, $logData, $logEntities);

        $this->processUpdates();
        $this->processActions();

        $this->logTimes($nodeClass, TRUE);
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
     * To be implemented in each NodeModule
     * Should set up any initial data structures, connections, and open any required files that the node needs to operate.
     * In the case of any errors that mean a successful sync is unlikely, a InitException MUST be thrown.
     * @param NodeEntity $nodeEntity
     */
    abstract protected function _init();

    /**
     * To be implemented in each NodeModule
     * The opposite of _init. It will always be the last call to the Node to close off any open connections, files, etc.
     * NB: Will be called even if a NodeException has been thrown but NOT any other (represents an irrecoverable error)
     */
    abstract protected function _deinit();

    /**
     * To be implemented in each NodeModule
     * Returns an subclass instance of AbstractGateway for provided entity type.
     * @param string $entityType
     * @return AbstractGateway|NULL $gateway
     */
    abstract protected function _createGateway($entityType);

}
