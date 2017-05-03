<?php
/**
 * @package Web\Widget
 * @author Matt Johnston
 * @copyright Copyright (c) 2014 LERO9 Ltd.
 * @license http://opensource.org/licenses/BSD-3-Clause BSD-3-Clause - Please view LICENSE.md for more information
 */

namespace Web\Widget;

class OrdersStatus extends BarWidget
{

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

            return $entityService->executeQueryAssoc('SELECT o.status AS k, COUNT(*) AS v FROM {order:o:status} WHERE o.status IS NOT NULL AND o.status NOT IN ("' . implode('", "', $exclude) . '") GROUP BY o.status');

        }else{

            return $entityService->executeQueryAssoc('SELECT o.status AS k, COUNT(*) AS v FROM {order:o:status} WHERE o.status IS NOT NULL GROUP BY o.status');

        }
    }

    function getTitle() {
        return 'Orders by Status';
    }

}