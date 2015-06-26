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
            'offset'=>20,
        )
    ),
    'node_types'=>array(
        'HOPS'=>array(
            'config'=>array(
                'logclear_time'=>array(
                    'label'=>'Number of days to keep log data for (minimum is 10)',
                    'type'=>'Text',
                    'default'=>'10',
                    'required'=>FALSE
                ),
            ),
        )
    )
);
