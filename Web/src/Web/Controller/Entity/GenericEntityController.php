<?php
/**
 * @package Web\Controller
 * @author Sean Yao
 * @author Andreas Gerhards <andreas@lero9.co.nz>
 * @copyright Copyright (c) 2014 LERO9 Ltd.
 * @license http://opensource.org/licenses/BSD-3-Clause BSD-3-Clause - Please view LICENSE.md for more information
 */

namespace Web\Controller\Entity;

use Zend\View\Model\ViewModel;
use Entity\Entity;

class GenericEntityController extends AbstractEntityController
{

    /**
     * Should be overridden with the required route name, to be used in URL generation
     * @return string
     */
    public function getRouteName(){
        return 'entity';
    }

    public function indexAction()
    {
        $types = $this->getTableGateway('entity_type')->select();

        return new ViewModel(array('types'=>$types));
    }

    protected function getEntityType()
    {
        return $this->getEvent()->getRouteMatch()->getParam('type');
    }

    /**
     * Returns an array of mass actions available on this grid
     * @return array
     */
    protected function getMassActions()
    {
        return array();
    }

}
