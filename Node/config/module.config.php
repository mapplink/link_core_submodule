<?php

return array (
    'service_manager'=>array(
        'invokables'=>array(
            'nodeService'=>'Node\Service\NodeService',
        ),
    ),
	'doctrine' => array(
        'driver' => array(
            'node_entities' => array(
                'class' => 'Doctrine\ORM\Mapping\Driver\AnnotationDriver',
                'cache' => 'array',
                'paths' => array(__DIR__ . '/../src/Node/Entity')
            ),
            'orm_default' => array(
                'drivers' => array(
                    'Node\Entity' => 'node_entities'
                )
            )
        )
    )
);
