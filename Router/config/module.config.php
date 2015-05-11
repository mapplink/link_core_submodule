<?php

return array (
    'service_manager'=>array(
        'invokables'=>array(
            'routerService'=>'Router\Service\RouterService',
            'transformFactory'=>'Router\Transform\TransformFactory',
            'filterFactory'=>'Router\Filter\FilterFactory',
            'transform_copy'=>'Router\Transform\CopyTransform',
            'transform_denormalize'=>'Router\Transform\DenormalizeTransform',
            'transform_set'=>'Router\Transform\SetTransform',
        ),
        'shared'=>array(
            'transform_copy'=>false,
            'transform_denormalize'=>false,
            'transform_set'=>false,
        ),
    ),
    'doctrine' => array(
        'driver' => array(
            'router_entities' => array(
                'class' => 'Doctrine\ORM\Mapping\Driver\AnnotationDriver',
                'cache' => 'array',
                'paths' => array(__DIR__ . '/../src/Router/Entity')
            ),
            'orm_default' => array(
                'drivers' => array(
                    'Router\Entity' => 'router_entities'
                )
            )
        )
    ),

    'controllers' => array(
        'invokables' => array(
            'Router\Controller\Console' => 'Router\Controller\Console',
        ),
    ),

    'console' => array(
        'router' => array(
            'routes' => array(
                'router-console' => array(
                    'options' => array(
                        'route'    => 'router <task> <id>',
                        'defaults' => array(
                            'controller' => 'Router\Controller\Console',
                            'action'     => 'run'
                        )
                    )
                )
            )
        )
    )
);
