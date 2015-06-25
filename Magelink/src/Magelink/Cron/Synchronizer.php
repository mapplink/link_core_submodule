<?php
/**
 * Magelink
 *
 * @category Magelink
 * @package Magelink\Cron
 * @author Matt Johnston
 * @author Andreas Gerhards <andreas@lero9.co.nz>
 * @copyright Copyright (c) 2014 LERO9 Ltd.
 * @license Commercial - All Rights Reserved
 */

namespace Magelink\Cron;

use Application\CronRunnable;
use Log\Service\LogService;
use Magelink\Exception\MagelinkException;
use Magelink\Exception\SyncException;
use Magelink\Exception\NodeException;
use Node\AbstractNode;
use Node\AbstractGateway;
use Node\Entity\Node as NodeEntity;
use Zend\ServiceManager\ServiceLocatorAwareInterface;


class Synchronizer extends CronRunnable
{

    /**
     * Performs any scheduled actions.
     */
    protected function _cronRun()
    {
        /** @var ServiceLocatorAwareInterface $serviceLocator */
        $serviceLocator = $this->getServiceLocator();
        /** @var LogService $logService */
        $logService = $serviceLocator->get('logService');
        $appConfig = $serviceLocator->get('Config');
        $typeConfig = $appConfig['node_types'];

        try{
            $nodesToUpdate = array();

            $nodes = $serviceLocator->get('nodeService')->getActiveNodes();
            foreach ($nodes as $nodeEntity) {
                $nodeId = $nodeEntity->getId();
                if ($nodeId) {
                    if (!($nodeEntity instanceof NodeEntity)) {
                        throw new MagelinkException('Invalid node type passed (' . get_class($nodeEntity) . ')!');
                    }

                    if (!isset($typeConfig[$nodeEntity->getType()])) {
                        throw new MagelinkException('Invalid type name, module not installed? ' . $nodeEntity->getType());
                    }
                    $thisTypeConfig = $typeConfig[$nodeEntity->getType()];

                    $className = '\\'.$thisTypeConfig['module'].'\\Node';
                    if (!class_exists($className)) {
                        throw new MagelinkException('Node class does not exist: ' . $className);
                    }

                    /** @var AbstractNode $node */
                    $node = new $className();
                    if ($node instanceof ServiceLocatorAwareInterface) {
                        $node->setServiceLocator($serviceLocator);
                    }

                    $logMessage = 'Cron synchronizer on node '.$nodeId.' finished ';
                    $logData = array('node id'=>$nodeId);

                    try{
                        $node->init($nodeEntity, $this->scheduledRun);
                        $logService->log(LogService::LEVEL_INFO, 'cron_sync_node', $logMessage.'(init)', $logData);
                        $node->retrieve();
                        $logService->log(LogService::LEVEL_INFO, 'cron_sync_node', $logMessage.'(retrieve)', $logData);
                        $nodesToUpdate[] = $node;
                    }catch (NodeException $nodeException) {
                        $message = 'Synchronizer error on node '.$node->getNodeId().': '.$nodeException->getMessage();
                        $logData = array_merge($logData, array(
                            $nodeException->getMessage(),
                            $nodeException->getTraceAsString()
                        ));
                        $logService->log(LogService::LEVEL_ERROR, 'cron_sync_node_ex', $message, $logData,
                                array('exception'=>$nodeException, 'node entity'=>$nodeEntity, 'node'=>$node));
                        echo PHP_EOL.$nodeException->getTraceAsString().PHP_EOL;
                    }
                }
            }

            foreach ($nodesToUpdate as $node) {
                $nodeId = $node->getNodeId();
                $logData = array('node id'=>$nodeId);
                try{
                    $node->update();
                    $logMessage = 'Cron synchronizer on node '.$nodeId.' finished (update)';
                    $logService->log(LogService::LEVEL_INFO, 'cron_sync_node', $logMessage, $logData);
                }catch (NodeException $nodeException) {
                    $logMessage = 'Synchronizer error updating node '.$nodeId.': '.$nodeException->getMessage();
                    $logData = array_merge($logData, array(
                        $nodeException->getMessage(),
                        $nodeException->getTraceAsString()
                    ));
                    $logService->log(LogService::LEVEL_ERROR, 'cron_sync_nupdex',
                        $logMessage, $logData, array('exception'=>$nodeException, 'node'=>$node));
                    echo PHP_EOL.$nodeException->getTraceAsString().PHP_EOL;
                }

                $logData = array('node id'=>$nodeId);
                try{
                    $node->deinit();
                    $logMessage = 'Cron synchronizer on node '.$nodeId.' finished (deinit)';
                    $logService->log(LogService::LEVEL_INFO, 'cron_sync_node', $logMessage, $logData);
                }catch (NodeException $nodeException) {
                    $logMessage = 'Synchronizer error (node) on node '.$nodeId.' deinit: '.$nodeException->getMessage();
                    $logData = array_merge($logData, array(
                        $nodeException->getMessage(),
                        $nodeException->getTraceAsString()
                    ));
                    $logService->log(LogService::LEVEL_ERROR, 'cron_sync_nodeex',
                        $logMessage, $logData, array('exception'=>$nodeException, 'node'=>$node));
                    echo PHP_EOL.$nodeException->getTraceAsString().PHP_EOL;
                }
            }
        }catch (SyncException $syncException) {
            $logService->log(LogService::LEVEL_ERROR,
                'cron_sync_syncex',
                'Synchronizer error (sync): '.$syncException->getMessage(),
                array($syncException->getMessage(), $syncException->getTraceAsString()),
                array('exception'=>$syncException)
            );
            echo PHP_EOL.$syncException->getTraceAsString().PHP_EOL;
        }catch (MagelinkException $magelinkException) {
            $logService->log(LogService::LEVEL_ERROR,
                'cron_sync_mageex',
                'Synchronizer error (mage): '.$magelinkException->getMessage(),
                array($magelinkException->getMessage(), $magelinkException->getTraceAsString()),
                array('exception'=>$magelinkException)
            );
            echo PHP_EOL.$magelinkException->getTraceAsString().PHP_EOL;
        }
    }

}