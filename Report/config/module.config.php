<?php

return array (
    'doctrine' => array(
        'driver' => array(
            'report_entities' => array(
                'class' => 'Doctrine\ORM\Mapping\Driver\AnnotationDriver',
                'cache' => 'array',
                'paths' => array(__DIR__ . '/../src/Report/Entity')
            ),
            'orm_default' => array(
                'drivers' => array(
                    'Report\Entity' => 'report_entities'
                )
            )
        )
    ),
);
