<?php
/**
 * @package Web\Helper
 * @author Sean Yao
 * @author Andreas Gerhards <andreas@lero9.co.nz>
 * @copyright Copyright (c) 2014 LERO9 Ltd.
 * @license http://opensource.org/licenses/BSD-3-Clause BSD-3-Clause - Please view LICENSE.md for more information
 */

namespace Web\Helper;


use Zend\Filter\Word\CamelCaseToDash;


class CRUDRouteGenerator
{

    /** @var \Web\Controller\BaseController $this->controller */
    protected $controller;
    /** @var string $this->routePrefix*/
    protected $routePrefix;


    /**
     * Constructor
     * @param \Web\Controller\BaseController $controller
     */
    public function __construct(\Web\Controller\BaseController $controller)
    {
        $this->controller = $controller;
        $this->setRoutePrefix();
        $this->controllerInvokableName = preg_replace('/Controller$/', '', get_class($this->controller)); // The name for the controller invokable
    }

    /**
     * Set up route prefix
     *
     * @param string $routePrefix
     */
    protected function setRoutePrefix($routePrefix = null)
    {
        if (!$routePrefix) {
            $classRelection = new \ReflectionClass(get_class($this->controller));
            $routePrefix = $classRelection->getShortName();
            $camelCaseToDashFilter = new CamelCaseToDash();
            $routePrefix = $camelCaseToDashFilter->filter($routePrefix);
            $routePrefix = strtolower($routePrefix);
            $routePrefix = preg_replace('/-controller$/i', '', $routePrefix);
        }

        $this->routePrefix = $routePrefix;
    }

    /**
     * Get the route name with prefix
     * @param  string $name
     * @return string
     */
    public function getRouteName($name)
    {
        return $this->routePrefix . '/'. $name;
    }

    /**
     * Get route config
     * @return array
     */
    public function getRouteConfig()
    {
        return array(

            $this->routePrefix=>array (
                'type'=>'Zend\Mvc\Router\Http\Literal',
                'options'=>array(
                    'route'=>'/' . $this->routePrefix,
                ),

                'child_routes'=>array(
                    'list'=>array(
                        'type'=>'Zend\Mvc\Router\Http\Segment',
                        'options'=>array(
                            'route'=>'/list[/:page]',
                            'defaults'=>array(
                                'controller'=>$this->controllerInvokableName,
                                'action'    =>'list',
                            ),
                            'constraints'=>array(
                                'page'    =>'[0-9]+',
                            ),
                        ),
                    ),

                    'edit'=>array(
                        'type'=>'Zend\Mvc\Router\Http\Segment',
                        'options'=>array(
                            'route'=>'/edit/:id',
                            'defaults'=>array(
                                'controller'=>$this->controllerInvokableName,
                                'action'    =>'edit',
                            ),
                            'constraints'=>array(
                                'page'    =>'[0-9]+',
                            ),
                        ),
                    ),

                    'create'=>array(
                        'type'=>'Zend\Mvc\Router\Http\Literal',
                        'options'=>array(
                            'route'=>'/create',
                            'defaults'=>array(
                                'controller'=>$this->controllerInvokableName,
                                'action'    =>'create',
                            ),
                        ),
                    ),

                    'delete'=>array(
                        'type'=>'Zend\Mvc\Router\Http\Segment',
                        'options'=>array(
                            'route'=>'/delete/:id',
                            'defaults'=>array(
                                'controller'=>$this->controllerInvokableName,
                                'action'    =>'delete',
                            ),
                        ),
                        'constraints'=>array(
                            'page'    =>'[0-9]+',
                        ),
                    ),

                ),
            ),
        );
    }

}
