<?php

namespace Entity\Cron;

use \Zend\ServiceManager\ServiceLocatorInterface;

class Tester implements \Application\CronRunnable, \Zend\ServiceManager\ServiceLocatorAwareInterface {


    /**
     * Checks whether we should run the cron task this run through.
     *
     * @param string $time The time of this cron run (rounded down to 5 minute intervals) as a HH:MM string
     * @return boolean
     */
    public function cronCheck($time) {
        return false; // Change to enable/disable
    }

    /**
     * Performs any scheduled actions.
     */
    public function cronRun() {
        /** @var \Entity\Service\EntityService $entityService */
        $entityService = $this->getServiceLocator()->get('entityService');

        $pickedRaw = $entityService->aggregateEntity(
            3, 'orderitem', FALSE,
            array('qty_picked'=>'SUM'),
            array('PARENT_ID'=>array(21302), 'product'=>'12345', 'ENTITY_ID'=>'123456'),
            array('PARENT_ID'=>'in', 'ENTITY_ID'=>'not_eq'));
        var_export($pickedRaw);
        return;


        $res = $entityService->locateEntity(
            3, 5, false, array (
                'PARENT_ID.hops_status' => 'new_pending',
            ),array (
                'PARENT_ID.hops_status' => 'eq',
            ),array (
                'limit' => 20,
                'offset' => 0,
                'order' =>
                    array (
                    ),
                'fkey' =>
                    array (
                        'PARENT_ID' => 'order',
                    ),
            )
        );
        foreach($res as $r){
            echo 'Returned entity ' . $r->getId() . ' ('.$r->getUniqueId().')'.PHP_EOL;
        }
        die();

        /*
         * [DEBUG:locate]           Entity\Service\EntityService->locateEntity:153    locateEntity - 3 - 5 - .
SD:
array (
  'PARENT_ID.hops_status' => 'new_pending',
)
; ST:
array (
  'PARENT_ID.hops_status' => 'eq',
)
; OPT:
array (
  'limit' => 20,
  'offset' => 0,
  'order' =>
  array (
  ),
  'fkey' =>
  array (
    'PARENT_ID' => 'order',
  ),
)
         */

        var_export($entityService->locateEntity(1, 'orderitem', false,
            array(
                'PARENT_ID.hops_status'=>'new',
            ),
            array(
                'PARENT_ID.hops_status'=>'eq',
            ),
            array('fkey'=>array(
                'PARENT_ID'=>'order',
            ))
        ));

        return;
        echo $entityService->parseQuery(trim(<<<EOF
SELECT * FROM {stockitem:p}
EOF
    ));
        return;

        var_dump($entityService->executeQuery(/*'SELECT * FROM {product:p1:name,type} LEFT JOIN {stockitem:si:available} ON si.parent_id = p1.entity_id WHERE p1.type = "simple";*/'
        SELECT DISTINCT
          o.entity_id AS order_id
        FROM
          {order:o}
          INNER JOIN {orderitem:oi:qty_picked,quantity,product:quantity >= qty_picked,qty_picked = NULL:where_type=OR}
            ON oi.parent_id = o.entity_id
          LEFT JOIN {stockitem:si:available,pickable} ON si.parent_id = oi.product AND si.pickable < (oi.quantity - oi.qty_picked)
          WHERE si.entity_id IS NULL AND oi.qty_picked IS NOT NULL
        ')); // AND EXISTS ({stockitem:si:::no_select=1,no_wrap=1,select=1,where=parent_id = oi.parent_id})
        echo PHP_EOL;
        die();

        return;
        //     public function locateEntityExtended ( $node_id, $entity_type, $store_id, $searchData, $searchType = array(), $options = array(), $extendedData = array() ) {

        $entityService->locateEntityExtended(1, 'order', 1, array('UNIQUE_ID'=>array('100000001', '100000002')), array('UNIQUE_ID'=>'in'), array(), array(
            'join'=>array(
                array(
                    'join_child'=>'parent_id',
                    'join_parent'=>'entity_id',
                    'entity_type_id'=>5,
                    'store_id'=>1,
                    'searchData'=>array(),
                    'searchType'=>array(),
                    'join_type'=>'inner',
                    'extra_join_att'=>array(
                        'product',
                    ),
                    'join'=>array(
                        array(
                            'join_child'=>'entity_id',
                            'join_parent'=>'val_product.value',
                            'entity_type_id'=>3,
                            'store_id'=>1,
                            'searchData'=>array('type'=>'simple'),
                            'searchType'=>array('type'=>'eq'),
                            'join_type'=>'inner',
                        ),
                    ),
                ),
            ),
        ));
    }

    /**
     * @var ServiceLocatorInterface The service locator
     */
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