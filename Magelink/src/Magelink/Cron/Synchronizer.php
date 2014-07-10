<?php

namespace Magelink\Cron;

use Zend\ServiceManager\ServiceLocatorInterface;
use Zend\ServiceManager\ServiceLocatorAwareInterface;
use Node\AbstractNode;
use Node\AbstractGateway;
use Magelink\Exception\MagelinkException;
use Magelink\Exception\SyncException;
use Magelink\Exception\NodeException;
use Application\CronRunnable;

class Synchronizer implements CronRunnable, ServiceLocatorAwareInterface {


    /**
     * Checks whether we should run the cron task this run through.
     * @param int $time The time of this cron run (rounded down to 5 minute intervals)
     * @return boolean
     */
    public function cronCheck($time)
    {
        if($time % 1800 == 0){ // 30 minute interval
            return true;
        }
        return false;
    }

    /**
     * Performs any scheduled actions.
     */
    public function cronRun()
    {
        $this->getServiceLocator()->get('logService')->log(\Log\Service\LogService::LEVEL_INFO, 'sync_run', 'Beginning synchronization run', array());

        $appConfig = $this->getServiceLocator()->get('Config');
        $typeConfig = $appConfig['node_types'];

        try{
            $nodesToUpdate = array();

            $nodes = $this->getServiceLocator()->get('nodeService')->getActiveNodes();
            foreach ($nodes as $nodeEntity) {
                if ($nodeEntity->getId()) {
                    if(!($nodeEntity instanceof \Node\Entity\Node)){
                        throw new MagelinkException('Invalid node type passed (' . get_class($nodeEntity) . ')!');
                    }

                    if(!isset($typeConfig[$nodeEntity->getType()])){
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
                    }catch(NodeException $ne){
                        $message = 'Uncaught exception while processing node '.$nodeEntity->getNodeId().': '
                            .$ne->getMessage();
                        $this->getServiceLocator()->get('logService')
                            ->log(\Log\Service\LogService::LEVEL_ERROR,
                                'nodeex',
                                $message,
                                array($ne->getMessage(), $ne->getTraceAsString()),
                                array('exception'=>$ne, 'node'=>$nodeEntity->getNodeId())
                            );
                        echo PHP_EOL.$ne->getTraceAsString().PHP_EOL;
                    }
                }
            }

            foreach($nodesToUpdate as $node){
                try{
                    $node->update();
                }catch(NodeException $ne){
                    $this->getServiceLocator()->get('logService')->log(\Log\Service\LogService::LEVEL_ERROR, 'nodeex', 'Uncaught exception while updating node ' . $node->getNodeId() . ': ' . $ne->getMessage(), array($ne->getMessage(), $ne->getTraceAsString()), array('exception'=>$ne, 'node'=>$nodeEntity->getNodeId()));
                    echo PHP_EOL.$ne->getTraceAsString().PHP_EOL;
                }

                try{
                    $node->deinit();
                }catch(NodeException $ne){
                    $this->getServiceLocator()->get('logService')->log(\Log\Service\LogService::LEVEL_ERROR, 'nodeex', 'Uncaught exception while updating node ' . $node->getNodeId() . ': ' . $ne->getMessage(), array($ne->getMessage(), $ne->getTraceAsString()), array('exception'=>$ne, 'node'=>$nodeEntity->getNodeId()));
                    echo PHP_EOL.$ne->getTraceAsString().PHP_EOL;
                }
            }

        }catch(MagelinkException $se){
            $this->getServiceLocator()->get('logService')->log(\Log\Service\LogService::LEVEL_ERROR, 'syncex', 'Uncaught exception during synchronization: ' . $se->getMessage(), array($se->getMessage(), $se->getTraceAsString()), array('exception'=>$se));
            echo PHP_EOL.$se->getTraceAsString().PHP_EOL;
        }

    }

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