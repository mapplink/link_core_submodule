<?php

return array (
    'service_manager'=>array(
        'invokables'=>array(
            'entityService'=>'Entity\Service\EntityService',
            'entityConfigService'=>'Entity\Service\EntityConfigService'
        ),
        'shared'=>array(
            'entityService'=>FALSE
        )
    ),
    'doctrine'=>array(
        'driver'=>array(
            'entity_entities'=>array(
                'class'=>'Doctrine\ORM\Mapping\Driver\AnnotationDriver',
                'cache'=>'array',
                'paths'=>array(__DIR__ . '/../src/Entity/Entity')
            ),
            'orm_default'=>array(
                'drivers'=>array(
                    'Entity\Entity'=>'entity_entities'
                )
            )
        )
    ),
    'controllers'=>array(
        'invokables'=>array(
            'Entity\Controller\Console'=>'Entity\Controller\Console'
        )
    ),
    'entity_class'=>array(
        'address'=>'\Entity\Wrapper\Address',
        'product'=>'\Entity\Wrapper\Product',
        'order'=>'\Entity\Wrapper\Order',
        'orderitem'=>'\Entity\Wrapper\Orderitem',
        'stockitem'=>'\Entity\Wrapper\Stockitem',
        'creditmemo'=>'\Entity\Wrapper\Creditmemo',
        'creditmemoitem'=>'\Entity\Wrapper\Creditmemoitem'
    ),
/*
    'magelink_cron'=>array(
        'entity_tester'=>array(
            'class'=>'\Entity\Cron\Tester',
            'interval'=>1,
            'offset'=>0
        )
    ),
*/
    'console'=>array(
        'router'=>array(
            'routes'=>array(
                'entity-console'=>array(
                    'options'=>array(
                        'route'=>'entity <task> <id>',
                        'defaults'=>array(
                            'controller'=>'Entity\Controller\Console',
                            'action'=>'run'
                        )
                    )
                )
            )
        )
    )
);
