<?php

return array (
	'service_manager'=>array(
		'invokables'=>array(
			'logService'=>'Log\Service\LogService',
		),
	),

    'doctrine' => array(
        'driver' => array(
            'log_entities' => array(
                'class' => 'Doctrine\ORM\Mapping\Driver\AnnotationDriver',
                'cache' => 'array',
                'paths' => array(__DIR__ . '/../src/Log/Entity')
            ),
            'orm_default' => array(
                'drivers' => array(
                    'Log\Entity' => 'log_entities'
                )
            )
        )
    ),

    'magelink_cron'=>array(
        'logclear'=>array(
            'class'=>'\Log\Cron\LogClear',
            'interval'=>180,
            'offset'=>0,
        )
    )
);
