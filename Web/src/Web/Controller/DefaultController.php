<?php
/**
 * @package Web\Controller
 * @author Sean Yao
 * @author Andreas Gerhards <andreas@lero9.co.nz>
 * @copyright Copyright (c) 2014 LERO9 Ltd.
 * @license http://opensource.org/licenses/BSD-3-Clause BSD-3-Clause - Please view LICENSE.md for more information
 */

namespace Web\Controller;

use Zend\View\Model\ViewModel;
use Magelink\Exception\MagelinkException;


class DefaultController extends BaseController
{

    /**
     * @return ViewModel
     */
    public function indexAction()
    {
        $username = $this->getCurrentUser()->getDisplayName();
        return new ViewModel(array('username'=>$username));
    }

    /**
     * @return void|ViewModel
     * @throws MagelinkException
     */
    public function dashboardAction()
    {
        $config = $this->getServiceLocator()->get('Config');
        $widgets = $config['dashboard_widgets'];
        $col1 = $col2 = $col3 = '';

        foreach($widgets as $type=>$config){
            /** @var \Web\Widget\AbstractWidget $obj */
            $obj = $this->getServiceLocator()->get('widget_'.$type);
            if (!$obj || !($obj instanceof \Web\Widget\AbstractWidget)) {
                throw new MagelinkException('Invalid widget type ' . $type);
                return;
            }

            $obj->load($config);
            $html = $obj->render($type);
            if($config['column'] == 1){
                $col1 .= $html;
            }else if($config['column'] == 2){
                $col2 .= $html;
            }else if($config['column'] == 3){
                $col3 .= $html;
            }else{
                throw new MagelinkException('Invalid column for widget ' . $type);
            }
        }

        return new ViewModel(array('col1'=>$col1, 'col2'=>$col2, 'col3'=>$col3));
    }

}
