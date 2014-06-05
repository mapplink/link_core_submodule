<?php

return array (
    
    'service_manager'=>array(
        'invokables'=>array(
            'configService'=>'Magelink\Service\ConfigService',
        ),

        'aliases' => array('zfcuser_doctrine_em' => 'Doctrine\ORM\EntityManager'),

        'factories' => array(
            'Magelink\Auth\UnauthorizedStrategy' => function($serviceLocator) {
                $config = $serviceLocator->get('BjyAuthorize\Config');
                return new \Magelink\Auth\UnauthorizedStrategy($config['template']);
            },

            'BjyAuthorize\Guard\Controller' => function($serviceLocator) {
                $config = $serviceLocator->get('BjyAuthorize\Config');

                return new \Magelink\Auth\ACLGuardController($config['guards']['BjyAuthorize\Guard\Controller'], $serviceLocator);
            },
        )
    ),
    
    'doctrine' => array(
        'driver' => array(
            'magelink_entities' => array(
                'class' => 'Doctrine\ORM\Mapping\Driver\AnnotationDriver',
                'cache' => 'array',
                'paths' => array(__DIR__ . '/../src/Magelink/Entity')
            ),
            'orm_default' => array(
                'drivers' => array(
                    'Magelink\Entity' => 'magelink_entities'
                )
            )
        )
    ),

    'magelink_cron' => array(
        'synchronizer'=>'\Magelink\Cron\Synchronizer'
    ),

);

