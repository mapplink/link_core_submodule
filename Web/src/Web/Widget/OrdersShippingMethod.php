<?php
/**
 * @package Web\Widget
 * @author Matt Johnston
 * @copyright Copyright (c) 2014 LERO9 Ltd.
 * @license http://opensource.org/licenses/BSD-3-Clause BSD-3-Clause - Please view LICENSE.md for more information
 */

namespace Web\Widget;

class OrdersShippingMethod extends BarWidget {

    /**
     * Should be overridden by child classes to implement data loading.
     *
     * @params array $options
     * @return mixed The loaded data
     */
    protected function _load($options=array()) {
        /** @var array|bool $exclude */
        $exclude = (isset($options['exclude']) ? $options['exclude'] : false);

        /** @var \Entity\Service\EntityService $entityService */
        $entityService = $this->getServiceLocator()->get('entityService');

        if($exclude){
            return $entityService->executeQueryAssoc('SELECT o.shipping_method AS k, COUNT(*) AS v FROM {order:o:shipping_method} WHERE o.shipping_method IS NOT NULL AND o.shipping_method NOT IN ("' . implode('", "', $exclude) . '") GROUP BY o.shipping_method');
        }else{
            return $entityService->executeQueryAssoc('SELECT o.shipping_method AS k, COUNT(*) AS v FROM {order:o:shipping_method} WHERE o.shipping_method IS NOT NULL GROUP BY o.shipping_method');
        }
    }

    function getTitle() {
        return 'Orders by Shipping Method';
    }

}