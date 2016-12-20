<?php
/**
 * Entity\Cron\Tester
 *
 * @category Magelink
 * @package Entity\Cron
 * @author Matt Johnston
 * @author Andreas Gerhards <andreas@lero9.co.nz>
 * @copyright Copyright (c) 2014 LERO9 Ltd.
 * @license Commercial - All Rights Reserved
 */

namespace Entity\Cron;

use Application\CronRunnable;


class Tester extends CronRunnable
{

    /**
     * Checks whether we should run the cron task this run through.
     * @param string $minutes
     * @return boolean
     */
    public function cronCheck($minutes)
    {
        return FALSE; // Change to enable/disable
    }

    /**
     * Performs any scheduled actions.
     */
    protected function _cronRun()
    {
        /** @var \Entity\Service\EntityService $entityService */
        $entityService = $this->getServiceLocator()->get('entityService');

        $response = $entityService->locateEntity(8, 'customer', FALSE,
            array('billing_address'=>36061, 'shipping_address'=>36061),
            array('billing_address'=>'all_eq', 'shipping_address'=>'all_eq'),
            array('where_type'=>'OR'),
            array('billing_address', 'shipping_address')
        );

        var_dump($response);

        return;
        die();

        $pickedRaw = $entityService->aggregateEntity(
            3, 'orderitem', FALSE,
            array('qty_picked'=>'SUM'),
            array('PARENT_ID'=>array(21302), 'product'=>'12345', 'ENTITY_ID'=>'123456'),
            array('PARENT_ID'=>'in', 'ENTITY_ID'=>'not_eq'));
        var_export($pickedRaw);
        return;


        $response = $entityService->locateEntity(3, 5, FALSE,
            array (
                'PARENT_ID.hops_status' => 'new_pending',
            ),
            array (
                'PARENT_ID.hops_status' => 'eq',
            ),
            array (
                'limit'=>20,
                'offset'=>0,
                'order'=>array(),
                'fkey'=>array('PARENT_ID' => 'order'),
            )
        );

        foreach ($response as $entity) {
            print 'Returned entity ' . $entity->getId() . ' ('.$entity->getUniqueId().')'.PHP_EOL;
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

        var_export($entityService->locateEntity(1, 'orderitem', FALSE,
            array('PARENT_ID.hops_status'=>'new'),
            array('PARENT_ID.hops_status'=>'eq'),
            array('fkey'=>array('PARENT_ID'=>'order'))
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
//     public function locateEntityExtended ($node_id, $entityType, $store_id, $searchData, $searchType = array(), $options = array(), $extendedData = array()) {

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

}
