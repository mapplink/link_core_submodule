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
use Magelink\Exception\MagelinkException;
use Magelink\Exception\SyncException;
use Magelink\Exception\NodeException;
use Node\AbstractNode;
use Node\AbstractGateway;
use Zend\ServiceManager\ServiceLocatorInterface;
use Zend\ServiceManager\ServiceLocatorAwareInterface;


class Synchronizer implements CronRunnable, ServiceLocatorAwareInterface
{

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
     * Checks whether we should run the cron task this run through.
     * @param int $minutes
     * @return boolean
     */
    public function cronCheck($minutes)
    {
        $run = !($minutes % 30 == 0);
        return $run;
    }

    /**
     * Performs any scheduled actions.
     */
    public function cronRun()
    {
        $this->getServiceLocator()->get('logService')->log(\Log\Service\LogService::LEVEL_INFO,
            'sync_run', 'Beginning synchronization run '.date('H:i:s d/m/y'), array());

        $appConfig = $this->getServiceLocator()->get('Config');
        $typeConfig = $appConfig['node_types'];

        try{
            $nodesToUpdate = array();

            $nodes = $this->getServiceLocator()->get('nodeService')->getActiveNodes();
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
                    if(!class_exists($className)){
                        throw new MagelinkException('Node class does not exist: ' . $className);
                    }

                    /** @var AbstractNode $node */
                    $node = new $className();
                    if($node instanceof ServiceLocatorAwareInterface){
                        // DI service locator
                        $node->setServiceLocator($this->getServiceLocator());
                    }

                    try{
                        $node->init($nodeEntity);
                        $node->retrieve();
                        $nodesToUpdate[] = $node;
                    }catch (NodeException $nodeException) {
                        $message = 'Uncaught exception while processing node '.$nodeEntity->getNodeId().': '
                            .$nodeException->getMessage();
                        $this->getServiceLocator()->get('logService')
                            ->log(\Log\Service\LogService::LEVEL_ERROR,
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
                    $this->getServiceLocator()->get('logService')->log(\Log\Service\LogService::LEVEL_ERROR,
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
                    $this->getServiceLocator()->get('logService')->log(\Log\Service\LogService::LEVEL_ERROR,
                        'nodeex',
                        'Uncaught node exception while updating node '.$node->getNodeId().': '.$nodeException->getMessage(),
                        array($nodeException->getMessage(), $nodeException->getTraceAsString()),
                        array('exception'=>$nodeException, 'node'=>$nodeEntity->getNodeId())
                    );
                    echo PHP_EOL.$nodeException->getTraceAsString().PHP_EOL;
                }
            }
        }catch (SyncException $syncException) {
            $this->getServiceLocator()->get('logService')->log(\Log\Service\LogService::LEVEL_ERROR,
                'syncex',
                'Uncaught exception during synchronization: '.$syncException->getMessage(),
                array($syncException->getMessage(), $syncException->getTraceAsString()),
                array('exception'=>$syncException)
            );
            echo PHP_EOL.$syncException->getTraceAsString().PHP_EOL;
        }catch (MagelinkException $magelinkException) {
            $this->getServiceLocator()->get('logService')->log(\Log\Service\LogService::LEVEL_ERROR,
                'mageex',
                'Uncaught exception during synchronization: '.$magelinkException->getMessage(),
                array($magelinkException->getMessage(), $magelinkException->getTraceAsString()),
                array('exception'=>$magelinkException)
            );
            echo PHP_EOL.$magelinkException->getTraceAsString().PHP_EOL;
        }
    }

}