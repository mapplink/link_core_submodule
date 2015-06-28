<?php
/**
 * @category Web
 * @package Web\Controller
 * @author Sean Yao
 * @author Andreas Gerhards <andreas@lero9.co.nz>
 * @copyright Copyright (c) 2014 LERO9 Ltd.
 * @license Commercial - All Rights Reserved
 */

namespace Web\Controller\CRUD;

use Web\Controller\CRUD\AbstractCRUDController;


class NodeAdminController extends AbstractCRUDController
{

    /**
     * Child classes should override to return the Entity class name that this CRUD controller works on.
     * @return string
     */
    protected function getEntityClass()
    {
        return 'Node\Entity\Node';
    }

    /**
     * @return array $listViewConfig
     */
    protected function getListViewConfig()
    {
        return array(
            'Id'=>array('linked'=>TRUE),
            'Name'=>array('linked'=>TRUE),
            'Type'=>array()
        );
    }

    /**
     * Set Filter Config
     * @return array
     */
    protected function getSearchFilterConfig()
    {
        return array(
            'name'=>array(
                'operators'=>array('contains', 'equals'),
                'label'=>'Name',
                'field'=>'name',
            ),
            'type'=>array(
                'operators'=>array('contains', 'equals'),
                'label'=>'Type',
                'field'=>'type',
            )
        );
    }

    /**
     * Get form
     * @param  object $object
     * @return Zend/Form/Form $form
     */
    protected function getForm($object)
    {
        $classRelection = new \ReflectionClass($this->formClassName);
        $form = $classRelection->newInstanceArgs(array(
            $this->getEntityManager(),
            $this->getServiceLocator()->get('Config')
        ));
        $form->bind($object);

        return $form;
    }
}