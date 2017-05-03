<?php
/**
 * @package Router\Controller
 * @author Sean Yao
 * @copyright Copyright (c) 2014 LERO9 Ltd.
 * @license http://opensource.org/licenses/BSD-3-Clause BSD-3-Clause - Please view LICENSE.md for more information
 */

namespace Router\Controller;

use Application\Controller\AbstractConsole;
use Magelink\Exception\MagelinkException;


class Console extends AbstractConsole
{

    /** @var array $this->_tasks */
    protected $_tasks = array(
        'refreshtransform',
        'distribute'
    );

    /**
     * @param $id
     * @throws MagelinkException
     */
    protected function refreshtransformTask($id)
    {
        /** @var \Router\Entity\RouterTransform $tf */
        $tf = $this->getServiceLocator()->get('Doctrine\ORM\EntityManager')
            ->getRepository('Router\Entity\RouterTransform')
            ->find($id);
        if (!$tf) {
            throw new MagelinkException('Could not find transform ' . $id);
        }

        /** @var \Entity\Service\EntityService $entityService */
        $entityService = $this->getServiceLocator()->get('entityService');
        /** @var \Entity\Service\EntityConfigService $entityConfigService */
        $entityConfigService = $this->getServiceLocator()->get('entityConfigService');
        /** @var \Router\Transform\TransformFactory $transformFactory */
        $transformFactory = $this->getServiceLocator()->get('transformFactory');
        /** @var \Router\Service\RouterService $routerService */
        $routerService = $this->getServiceLocator()->get('routerService');

        $atts = array_values($entityConfigService->getAttributesCode($tf->getEntityTypeId()));

        $tfObj = $transformFactory->getTransform($tf);
        if(!$tfObj){
            $this->getServiceLocator()->get('logService')
                ->log(\Log\Service\LogService::LEVEL_ERROR,
                    'bad_trans',
                    'processTransforms - invalid transform or error creating',
                    array('tfid'=>$tf->getTransformid(), 'type'=>$tf->getTransformType(), 'attributes'=>$atts)
                );
            throw new MagelinkException('Invalid transform type');
        }

        $att = $entityConfigService->getAttribute($tf->getSrcAttribute());

        /** @var \Entity\Entity[] $items */
        $items = $entityService->locateEntity(
            0,
            $tf->getEntityTypeId(),
            FALSE,
            array($att['code']=>null),
            array($att['code']=>'notnull'),
            array(),
            $atts
        );

        foreach ($items as $entity) {
            $skip = !$routerService->checkFiltersTransform(
                $entity,
                $tf,
                \Entity\Update::TYPE_UPDATE,
                $entity->getAllSetData()
            );

            if ($skip) {
                // Some filter blocked
                $this->getServiceLocator()->get('logService')
                    ->log(\Log\Service\LogService::LEVEL_INFO,
                        'rej_transform',
                        'processTransforms - rejected by filter - from cmdline - ' . $entity->getId() . ' - ' . $tf->getTransformId() . ' (' . get_class($tfObj) . ')',
                        array('tfid'=>$tf->getTransformid(), 'type'=>$tf->getTransformType(), 'attributes'=>$atts),
                        array('entity'=>$entity)
                    );
                continue;
            }

            if ($tfObj->init($entity, FALSE, $tf, $entity->getAllSetData())) {
                $this->getServiceLocator()->get('logService')
                    ->log(\Log\Service\LogService::LEVEL_INFO,
                        'app_transform',
                        'processTransforms - from cmdline - ' . $entity->getId() . ' - ' . $tf->getTransformId() . ' (' . get_class($tfObj) . ')',
                        array('tfid'=>$tf->getTransformid(), 'type'=>$tf->getTransformType(), 'attributes'=>$atts),
                        array('entity'=>$entity)
                    );
                $data = $tfObj->apply();
                if($data && count($data)){
                    // Silently update the entity with the given data. We can't activate normal update as we don't have a source node.
                    $entityService->silentUpdateEntity($entity, $data, FALSE);
                }
            }
        }

    }

    /**
     * @param $id
     */
    protected function distributeTask($id) {}

}