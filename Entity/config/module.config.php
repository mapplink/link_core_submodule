<?php

return array (
	'service_manager' => array(
		'invokables' => array(
			'entityService' => 'Entity\Service\EntityService',
			'entityConfigService' => 'Entity\Service\EntityConfigService',
		),
	),

    'doctrine' => array(
        'driver' => array(
            'entity_entities' => array(
                'class' => 'Doctrine\ORM\Mapping\Driver\AnnotationDriver',
                'cache' => 'array',
                'paths' => array(__DIR__ . '/../src/Entity/Entity')
            ),
            'orm_default' => array(
                'drivers' => array(
                    'Entity\Entity' => 'entity_entities'
                )
            )
        )
    ),

    'controllers' => array(
        'invokables' => array(
            'Entity\Controller\Console' => 'Entity\Controller\Console',
        ),
    ),

    'entity_class' => array(
        'order'=>'\Entity\Wrapper\Order',
        'address'=>'\Entity\Wrapper\Address',
        'orderitem'=>'\Entity\Wrapper\Orderitem',
        'product'=>'\Entity\Wrapper\Product',
        'creditmemo'=>'\Entity\Wrapper\Creditmemo',
        'creditmemoitem'=>'\Entity\Wrapper\Creditmemoitem'
    ),

    'magelink_cron' => array(
        'entity_tester'=>'\Entity\Cron\Tester',
    ),

    'console' => array(
        'router' => array(
            'routes' => array(
                'entity-console' => array(
                    'options' => array(
                        'route'    => 'entity <task> <id>',
                        'defaults' => array(
                            'controller' => 'Entity\Controller\Console',
                            'action'     => 'run'
                        )
                    )
                )
            )
        )
    )
);
