<?php
/**
 * @package Magelink\Cron
 * @author Andreas Gerhards <andreas@lero9.co.nz>
 * @copyright Copyright (c) 2017 LERO9 Ltd.
 * @license Commercial - All Rights Reserved
 */

namespace Magelink\Cron;

use Application\CronRunnable;
use Log\Service\LogService;
use Magelink\Exception\MagelinkException;
use Magelink\Exception\SyncException;
use Magelink\Exception\NodeException;
use Node\AbstractNode;
use Node\Entity\Node as NodeEntity;


class Retrieval extends CronRunnable
{

    /**
     * Performs any scheduled actions.
     */
    protected function _cronRun()
    {
        /** @var array $appConfig */
        $appConfig = $this->getServiceLocator()->get('Config');

        try{
            $nodes = $this->getServiceLocator()->get('nodeService')->getActiveNodes();
            /** @var NodeEntity $nodeEntity */
            foreach ($nodes as $nodeEntity) {
                $nodeId = $nodeEntity->getId();
                if ($nodeId && $nodeEntity->getType() != 'accredo') {
                    /** @var AbstractNode $node */
                    $node = self::getNodeFromNodeEntity($nodeEntity, $this->getServiceLocator());
                    $logData = array('node id'=>$nodeId);

                    try{
                        $node->init($nodeEntity, $this->scheduledRun);
                        $logMessage = 'Cron "retrieval" finished init on node '.$nodeId;
                        $this->_logService->log(LogService::LEVEL_INFO, $this->getLogCode().'_node', $logMessage, $logData);

                        $node->retrieve();
                        $logMessage = 'Cron "retrieval" finished retrieve on node '.$nodeId;
                        $this->_logService->log(LogService::LEVEL_INFO, $this->getLogCode().'_node', $logMessage, $logData);
                    }catch (NodeException $nodeException) {
                        $logMessage = 'Synchronizer error on node '.$node->getNodeId().': '.$nodeException->getMessage();
                        $logData = array_merge($logData, array(
                            $nodeException->getMessage(),
                            $nodeException->getTraceAsString()
                        ));
                        $this->_logService->log(LogService::LEVEL_ERROR, $this->getLogCode().'_node_ex', $logMessage, $logData,
                                array('exception'=>$nodeException, 'node entity'=>$nodeEntity, 'node'=>$node));
                        echo PHP_EOL.$nodeException->getTraceAsString().PHP_EOL;
                    }

                    $logData = array('node id'=>$nodeId);
                    try{
                        $node->deinit();
                        $logMessage = 'Cron "retrieval" finished deinit on node '.$nodeId;
                        $this->_logService->log(LogService::LEVEL_INFO, $this->getLogCode().'_node', $logMessage, $logData);
                    }catch (NodeException $nodeException) {
                        $logMessage = 'Synchronizer error (node) on node '.$nodeId.' deinit: '.$nodeException->getMessage();
                        $logData = array_merge($logData, array(
                            $nodeException->getMessage(),
                            $nodeException->getTraceAsString()
                        ));
                        $this->_logService->log(LogService::LEVEL_ERROR, $this->getLogCode().'_nodeex',
                            $logMessage, $logData, array('exception'=>$nodeException, 'node'=>$node));
                        echo PHP_EOL.$nodeException->getTraceAsString().PHP_EOL;
                    }
                }
            }
        }catch (SyncException $syncException) {
            $this->_logService->log(LogService::LEVEL_ERROR, $this->getLogCode().'_syncex',
                'Synchronizer error (sync): '.$syncException->getMessage(),
                array($syncException->getMessage(), $syncException->getTraceAsString()),
                array('exception'=>$syncException)
            );
            echo PHP_EOL.$syncException->getTraceAsString().PHP_EOL;
        }catch (MagelinkException $magelinkException) {
            $this->_logService->log(LogService::LEVEL_ERROR, $this->getLogCode().'_mageex',
                'Synchronizer error (mage): '.$magelinkException->getMessage(),
                array($magelinkException->getMessage(), $magelinkException->getTraceAsString()),
                array('exception'=>$magelinkException)
            );
            echo PHP_EOL.$magelinkException->getTraceAsString().PHP_EOL;
        }
    }

}
