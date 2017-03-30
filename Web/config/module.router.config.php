<?php
/**
 * @category Email
 * @package Form
 * @author Seo Yao
 * @author Andreas Gerhards <andreas@lero9.co.nz>
 * @copyright Copyright (c) 2014- LERO9 Ltd.
 * @license Commercial - All Rights Reserved
 */

$routeConfig = array(
    'router'=>array(
        'routes'=>array(
            'home'=>array(
                'type'=>'Zend\Mvc\Router\Http\Literal',
                'options'=>array(
                    'route'=>'/',
                    'defaults'=>array(
                        'controller'=>'Web\Controller\Default',
                        'action'=>'index',
                    ),
                ),
            ),
            'dashboard'=>array(
                'type'=>'Zend\Mvc\Router\Http\Literal',
                'options'=>array(
                    'route'=>'/dashboard',
                    'defaults'=>array(
                        'controller'=>'Web\Controller\Default',
                        'action'=>'dashboard',
                    ),
                ),
            ),
            'query'=>array(
                'type'=>'Zend\Mvc\Router\Http\Literal',
                'options'=>array(
                    'route'=>'/mlql',
                    'defaults'=>array(
                        'controller'=>'Web\Controller\Query',
                        'action'=>'index',
                    ),
                ),
            ),

            'entity'=>array(
                'type'=>'segment',
                'options'=>array(
                    'route'=>'/entities[/:action][/:type][?entity_id=:entity_id]',
                    'constraints'=>array(
                        'action'=>'[a-zA-Z][a-zA-Z0-9_-]*',
                        'type'=>'[a-zA-Z_]+',
                        'entity_id'=>'[0-9]*',
                    ),
                    'defaults'=>array(
                        'controller'=>'Web\Controller\Entity\GenericEntity',
                        'action'=>'index',
                    ),
                ),
            ),

            'zfcuser'=>array(
                'child_routes'=>array(
                    'sendresetlink'=>array(
                        'type'=>'Literal',
                        'options'=>array(
                            'route'=>'/send-reset-link',
                            'defaults'=>array(
                                'controller'=>'Web\Controller\CRUD\ZfcUser',
                                'action'=>'sendResetPasswordLink',
                            ),
                        ),
                    ),

                    'resetpwd'=>array(
                        'type'=>'Segment',
                        'options'=>array(
                            'route'=>'/password-reset/:hash',
                            'defaults'=>array(
                                'controller'=>'Web\Controller\CRUD\ZfcUser',
                                'action'=>'resetPasswordWithUserHash',
                            )
                        )
                    )
                )
            )
        )
    )
);

$controllers = array(
    'Web\Controller\CRUD\UserAdminController',
    'Web\Controller\CRUD\LocationAdminController',
    'Web\Controller\CRUD\EmailSenderAdminController',
    'Web\Controller\CRUD\EmailTemplateAdminController',
    'Web\Controller\CRUD\ConfigAdminController',
    'Web\Controller\CRUD\CronjobAdminController',
    'Web\Controller\CRUD\LogEntryAdminController',
    'Web\Controller\CRUD\EmailLogAdminController',
    'Web\Controller\CRUD\AuditLogAdminController',
    'Web\Controller\CRUD\NodeAdminController',
    'Web\Controller\CRUD\EmailTemplateParamAdminController',
    'Web\Controller\CRUD\UserAdminController'
);

foreach ($controllers as $className) {
    $className = new \ReflectionClass($className);
    $controllerRoute = $className->newInstance()->getRouteGenerator()->getRouteConfig();

    $routeConfig['router']['routes'] =  array_merge(
        $routeConfig['router']['routes'],
        $controllerRoute
    );
}

return $routeConfig;
