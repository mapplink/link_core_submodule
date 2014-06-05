<?php

return array (
	'doctrine' => array(
        'driver' => array(
            'license_entities' => array(
                'class' => 'Doctrine\ORM\Mapping\Driver\AnnotationDriver',
                'cache' => 'array',
                'paths' => array(__DIR__ . '/../src/License/Entity')
            ),
            'orm_default' => array(
                'drivers' => array(
                    'License\Entity' => 'license_entities'
                )
            )
        )
    ),
);
