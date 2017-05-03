<?php
/**
 * @package Magelink\Cron
 * @author Matt Johnston
 * @author Andreas Gerhards <andreas@lero9.co.nz>
 * @copyright Copyright (c) 2014 LERO9 Ltd.
 * @license http://opensource.org/licenses/BSD-3-Clause BSD-3-Clause - Please view LICENSE.md for more information
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
        /** @var array $appConfig */
        $appConfig = $this->getServiceLocator()->get('Config');

        try{
            $nodesToUpdate = array();

            $nodes = $this->getServiceLocator()->get('nodeService')->getActiveNodes();
            foreach ($nodes as $nodeEntity) {
                $nodeId = $nodeEntity->getId();
                if ($nodeId) {
                    /** @var AbstractNode $node */
                    $node = self::getNodeFromNodeEntity($nodeEntity, $this->getServiceLocator());
                    $logData = array('node id'=>$nodeId);

                    try{
                        $node->init($nodeEntity, $this->scheduledRun);
                        $logMessage = 'Cron synchronizer finished init on node '.$nodeId;
                        $this->_logService->log(LogService::LEVEL_INFO, 'crn_sync_node', $logMessage, $logData);
                        $nodesToDeinit[] = $node;

                        $node->retrieve();
                        $logMessage = 'Cron synchronizer finished retrieve on node '.$nodeId;
                        $this->_logService->log(LogService::LEVEL_INFO, 'crn_sync_node', $logMessage, $logData);
                        $nodesToUpdate[] = $node;
                    }catch (NodeException $nodeException) {
                        $logMessage = 'Synchronizer error on node '.$node->getNodeId().': '.$nodeException->getMessage();
                        $logData = array_merge($logData, array(
                            $nodeException->getMessage(),
                            $nodeException->getTraceAsString()
                        ));
                        $this->_logService->log(LogService::LEVEL_ERROR, 'crn_sync_node_ex', $logMessage, $logData,
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
                    $logMessage = 'Cron synchronizer finished update on node '.$nodeId;
                    $this->_logService->log(LogService::LEVEL_INFO, 'crn_sync_node', $logMessage, $logData);
                }catch (NodeException $nodeException) {
                    $logMessage = 'Synchronizer error updating node '.$nodeId.': '.$nodeException->getMessage();
                    $logData = array_merge($logData, array(
                        $nodeException->getMessage(),
                        $nodeException->getTraceAsString()
                    ));
                    $this->_logService->log(LogService::LEVEL_ERROR, 'crn_sync_nupdex',
                        $logMessage, $logData, array('exception'=>$nodeException, 'node'=>$node));
                    echo PHP_EOL.$nodeException->getTraceAsString().PHP_EOL;
                }
            }

            foreach ($nodesToDeinit as $node) {
                $nodeId = $node->getNodeId();
                $logData = array('node id'=>$nodeId);
                try{
                    $node->deinit();
                    $logMessage = 'Cron synchronizer finished deinit on node '.$nodeId;
                    $this->_logService->log(LogService::LEVEL_INFO, 'crn_sync_node', $logMessage, $logData);
                }catch (NodeException $nodeException) {
                    $logMessage = 'Synchronizer error (node) on node '.$nodeId.' deinit: '.$nodeException->getMessage();
                    $logData = array_merge($logData, array(
                        $nodeException->getMessage(),
                        $nodeException->getTraceAsString()
                    ));
                    $this->_logService->log(LogService::LEVEL_ERROR, 'crn_sync_nodeex',
                        $logMessage, $logData, array('exception'=>$nodeException, 'node'=>$node));
                    echo PHP_EOL.$nodeException->getTraceAsString().PHP_EOL;
                }
            }
        }catch (SyncException $syncException) {
            $this->_logService->log(LogService::LEVEL_ERROR, 'crn_sync_syncex',
                'Synchronizer error (sync): '.$syncException->getMessage(),
                array($syncException->getMessage(), $syncException->getTraceAsString()),
                array('exception'=>$syncException)
            );
            echo PHP_EOL.$syncException->getTraceAsString().PHP_EOL;
        }catch (MagelinkException $magelinkException) {
            $this->_logService->log(LogService::LEVEL_ERROR, 'crn_sync_mageex',
                'Synchronizer error (mage): '.$magelinkException->getMessage(),
                array($magelinkException->getMessage(), $magelinkException->getTraceAsString()),
                array('exception'=>$magelinkException)
            );
            echo PHP_EOL.$magelinkException->getTraceAsString().PHP_EOL;
        }
    }

}
