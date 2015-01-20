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

use Magelink\Exception\MagelinkException;
use Magelink\Exception\GatewayException;
use \Zend\ServiceManager\ServiceLocatorAwareInterface;
use \Zend\ServiceManager\ServiceLocatorInterface;

abstract class AbstractNode implements ServiceLocatorAwareInterface {

    /**
     * @var \Node\AbstractGateway[]
     */
    protected $_gateway = array();

    /**
     * @var \Node\Entity\Node
     */
    protected $_entity = null;

    /**
     * @var array
     */
    protected $_config = null;

    /**
     * @var array
     */
    protected $_typeConfig = null;

    /**
     * Sets up internal data structures, calls local _init method, and creates appropriate gateways.
     *
     * @param Entity\Node $nodeEntity
     * @see _init()
     */
    public function init( \Node\Entity\Node $nodeEntity ){
        $this->_entity = $nodeEntity;
        $nodeEntity->loadSimpleData();
        $this->_config = $nodeEntity->getSimpleData();

        $this->getServiceLocator()->get('logService')->log(\Log\Service\LogService::LEVEL_INFO,
            'init',
            'AbstractNode completed init',
            array('node'=>get_class($this), 'id'=>$nodeEntity->getNodeId()),
            array('node'=>$this)
        );

        $appConfig = $this->getServiceLocator()->get('Config');
        $this->_typeConfig = $appConfig['node_types'][$this->_entity->getType()];

        $this->_init($nodeEntity);
    }

    public function getNodeId(){
        return $this->_entity->getId();
    }

    public function getTitle(){
        return $this->_entity->getName();
    }

    /**
     * @see _deinit()
     */
    public function deinit(){
        $this->_deinit();
    }

    /**
     * Lazy-load a gateway for the provided entity type (providing DI and initialization)
     * @param $entityType
     * @return AbstractGateway
     */
    protected function _lazyLoad($entityType){
        $gateway = $this->_createGateway($entityType);
        if($gateway instanceof ServiceLocatorAwareInterface){
            $gateway->setServiceLocator($this->getServiceLocator());
        }
        if($gateway){
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
        if($gateways == null){
            $gateways = $this->_typeConfig['entity_type_support'];
        }

        $this->getServiceLocator()->get('logService')->log(\Log\Service\LogService::LEVEL_INFO,
            'retrieve',
            'AbstractNode retrieve',
            array('gateways'=>$gateways),
            array('node'=>$this)
        );

        foreach ($gateways as $gateway) {
            if(!isset($this->_gateway[$gateway])){
                // Lazy-load gateway for entity type
                $this->_gateway[$gateway] = $this->_lazyLoad($gateway);
            }
            if ($this->_gateway[$gateway]) {
                try{
                    $this->_gateway[$gateway]->retrieve();
                }catch (GatewayException $gatewayException) {
                    $message = 'Uncaught exception while processing node '.$gatewayException->getNodeId().': '
                        .$gatewayException->getMessage();
                    $this->getServiceLocator()->get('logService')
                        ->log(\Log\Service\LogService::LEVEL_ERROR,
                            'gatewayex',
                            $message,
                            array($gatewayException->getMessage(), $gatewayException->getTraceAsString()),
                            array('exception'=>$gatewayException, 'node'=>$gatewayException->getNodeId())
                        );
                    print PHP_EOL.$gatewayException->getTraceAsString().PHP_EOL;
                }
            }
        }
    }

    /**
     * Updates all data into the node’s source - should load and collapse all pending updates and call writeUpdates,
     *   as well as loading and sequencing all actions.
     */
    public function update()
    {
        /** @var \Node\Service\NodeService $nodeService */
        $nodeService = $this->getServiceLocator()->get('nodeService');

        $updates = $this->getServiceLocator()->get('nodeService')->getPendingUpdates($this->_entity);
        $actions = $this->getServiceLocator()->get('nodeService')->getPendingActions($this->_entity);

        $this->getServiceLocator()->get('logService')
            ->log(\Log\Service\LogService::LEVEL_INFO,
                'update',
                'AbstractNode update: '.count($updates).' updates, '.count($actions).' actions.',
                array(),
                array('node'=>$this,'updates'=>$updates, 'actions'=>$actions)
            );

        // Separate all updates into an array for each entity type
        $updatesByType = array();
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

            $attributes = $nodeService->getSubscribedAttributeCodes($this->getNodeId(), $entityType);

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
                        $this->getServiceLocator()->get('logService')
                            ->log(\Log\Service\LogService::LEVEL_INFO,
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
                $message = $entityId.' to '.$this->getNodeId();
                $this->getServiceLocator()->get('logService')
                    ->log(\Log\Service\LogService::LEVEL_INFO,
                        'push_update',
                        'Pushing update for '.$message,
                        array(
                            'attributes'=>$update['attributes'],
                            'type'=>$update['type'],
                            'combined'=>$update['combined']
                        ),
                        array('entity'=>$entityId, 'node'=>$this)
                    );

                try{
                    $this->_gateway[$entityType]->writeUpdates(
                        $update['entity'],
                        $update['attributes'],
                        $update['type']
                    );
                }catch (GatewayException $gatewayException) {
                    $message = 'Exception during update processing for '.$message.': '.$gatewayException->getMessage();
                    $this->getServiceLocator()->get('logService')
                        ->log(\Log\Service\LogService::LEVEL_ERROR,
                            'update_ex_gw',
                            $message,
                            array($gatewayException->getMessage(), $gatewayException->getTraceAsString()),
                            array('exception'=>$gatewayException)
                        );
                    unset($this->_gateway[$entityType]);
                    break;
                }catch (MagelinkException $exception) {
                    $message = 'Exception during update processing for '.$message.': '.$exception->getMessage();
                    $this->getServiceLocator()->get('logService')
                        ->log(\Log\Service\LogService::LEVEL_ERROR,
                            'update_ex_ml',
                            $message,
                            array($exception->getMessage(), $exception->getTraceAsString()),
                            array('exception'=>$exception)
                        );
                    throw new \Magelink\Exception\NodeException($message, 0, $exception);
                    break;
                }

                foreach ($entityTypeUpdates[$entityId] as $updateToBeMarkedAsCompleted) {
                    $nodeService->setUpdateStatus($this->_entity, $updateToBeMarkedAsCompleted, 1);
                }
            }
        }

        foreach ($actions as $action) {
            /* @var $action \Entity\Action */
            $entityType = $action->getEntity()->getTypeStr();
            if (!isset($this->_gateway[$entityType])) {
                // Lazy-load gateway for entity type
                $this->_gateway[$entityType] = $this->_lazyLoad($entityType);
            }
            try{
                $result = TRUE;
                if ($this->_gateway[$entityType]) {
                    $message = $action->getId().' to '.$this->getNodeId().' ('.$action->getEntity()->getUniqueId().')';
                    $this->getServiceLocator()->get('logService')->log(\Log\Service\LogService::LEVEL_INFO,
                        'send_action',
                        'Sending action '.$message,
                        array($action->getId()),
                        array('entity'=>$action->getEntity(), 'node'=>$this)
                    );
                    $result = $this->_gateway[$entityType]->writeAction($action);
                }
                if ($result) {
                    $nodeService->setActionStatus($this->_entity, $action, 1);
                }
            }catch (GatewayException $gatewayException) {
                $message = 'Exception during action processing for '.$message.': '.$gatewayException->getMessage();
                $this->getServiceLocator()->get('logService')->log(\Log\Service\LogService::LEVEL_ERROR,
                    'action_ex_gw',
                    $message,
                    array($gatewayException->getMessage(), $gatewayException->getTraceAsString()),
                    array('exception'=>$gatewayException)
                );
                unset($this->_gateway[$entityType]);
            }catch (MagelinkException $exception) {
                $message = 'Exception during action processing for '.$message.': '.$exception->getMessage();
                $this->getServiceLocator()->get('logService')->log(\Log\Service\LogService::LEVEL_ERROR,
                    'action_ex_ml',
                    $message,
                    array($exception->getMessage(), $exception->getTraceAsString()),
                    array('exception'=>$exception)
                );
                throw new \Magelink\Exception\NodeException($message, 0, $exception);
            }
        }
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
     * @param Entity\Node $nodeEntity
     */
    protected abstract function _init(\Node\Entity\Node $nodeEntity );

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

    protected $_serviceLocator;

    /**
     * Set service locator
     *
     * @param ServiceLocatorInterface $serviceLocator
     */
    public function setServiceLocator(ServiceLocatorInterface $serviceLocator)
    {
        $this->_serviceLocator = $serviceLocator;
    }

    /**
     * Get service locator
     *
     * @return ServiceLocatorInterface
     */
    public function getServiceLocator()
    {
        return $this->_serviceLocator;
    }

}