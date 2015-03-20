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
use Zend\ServiceManager\ServiceLocatorAwareInterface;


class Synchronizer extends CronRunnable
{

    /**
     * Performs any scheduled actions.
     */
    public function cronRun()
    {
        $serviceLocator = $this->getServiceLocator();
        $appConfig = $serviceLocator->get('Config');
        $typeConfig = $appConfig['node_types'];

        try{
            $nodesToUpdate = array();

            $nodes = $serviceLocator->get('nodeService')->getActiveNodes();
            foreach ($nodes as $nodeEntity) {
                if ($nodeEntity->getId()) {
                    if (!($nodeEntity instanceof \Node\Entity\Node)) {
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

                    try{
                        $node->init($nodeEntity);
                        $node->retrieve();
                        $nodesToUpdate[] = $node;
                    }catch (NodeException $nodeException) {
                        $message = 'Uncaught exception while processing node '.$nodeEntity->getNodeId().': '
                            .$nodeException->getMessage();
                        $serviceLocator->get('logService')->log(LogService::LEVEL_ERROR,
                                'nodeex',
                                $message,
                                array($nodeException->getMessage(), $nodeException->getTraceAsString()),
                                array('exception'=>$nodeException, 'node'=>$nodeEntity->getNodeId())
                            );
                        echo PHP_EOL.$nodeException->getTraceAsString().PHP_EOL;
                    }
                }
            }

            foreach ($nodesToUpdate as $node) {
                try{
                    $node->update();
                }catch (NodeException $nodeException) {
                    $serviceLocator->get('logService')->log(LogService::LEVEL_ERROR,
                        'nodeex',
                        'Uncaught exception while updating node '.$node->getNodeId().': '.$nodeException->getMessage(),
                        array($nodeException->getMessage(), $nodeException->getTraceAsString()),
                        array('exception'=>$nodeException, 'node'=>$nodeEntity->getNodeId())
                    );
                    echo PHP_EOL.$nodeException->getTraceAsString().PHP_EOL;
                }

                try{
                    $node->deinit();
                }catch (NodeException $nodeException) {
                    $serviceLocator->get('logService')->log(LogService::LEVEL_ERROR,
                        'nodeex',
                        'Uncaught node exception while updating node '.$node->getNodeId().': '.$nodeException->getMessage(),
                        array($nodeException->getMessage(), $nodeException->getTraceAsString()),
                        array('exception'=>$nodeException, 'node'=>$nodeEntity->getNodeId())
                    );
                    echo PHP_EOL.$nodeException->getTraceAsString().PHP_EOL;
                }
            }
        }catch (SyncException $syncException) {
            $serviceLocator->get('logService')->log(LogService::LEVEL_ERROR,
                'syncex',
                'Uncaught exception during synchronization: '.$syncException->getMessage(),
                array($syncException->getMessage(), $syncException->getTraceAsString()),
                array('exception'=>$syncException)
            );
            echo PHP_EOL.$syncException->getTraceAsString().PHP_EOL;
        }catch (MagelinkException $magelinkException) {
            $serviceLocator->get('logService')->log(LogService::LEVEL_ERROR,
                'mageex',
                'Uncaught exception during synchronization: '.$magelinkException->getMessage(),
                array($magelinkException->getMessage(), $magelinkException->getTraceAsString()),
                array('exception'=>$magelinkException)
            );
            echo PHP_EOL.$magelinkException->getTraceAsString().PHP_EOL;
        }
    }

}