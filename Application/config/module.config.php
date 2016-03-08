<?php
/**
 * Application config
 * @category Application
 * @author Andreas Gerhards <andreas@lero9.co.nz>
 * @copyright Copyright (c) 2015 LERO9 Ltd.
 * @license Commercial - All Rights Reserved
 */

return array(
    'doctrine'=>array(
        'driver'=>array(
            'application_entities'=>array(
                'class'=>'Doctrine\ORM\Mapping\Driver\AnnotationDriver',
                'cache'=>'array',
                'paths'=>array(__DIR__.'/../src/Application/Entity')
            ),
            'orm_default'=>array(
                'drivers'=>array(
                    'Application\Entity'=>'application_entities'
                )
            )
        )
    ),
    'service_manager'=>array(
        'invokables'=>array(
            'applicationConfigService'=>'Application\Service\ApplicationConfigService'
        ),
        'abstract_factories'=>array(
            'Zend\Cache\Service\StorageCacheAbstractServiceFactory',
            'Zend\Log\LoggerAbstractServiceFactory',
        ),
        'aliases'=>array(
            'translator'=>'MvcTranslator',
        ),
    ),
    'translator'=>array(
        'locale'=>'en_US',
        'translation_file_patterns'=>array(
            array(
                'type'=>'gettext',
                'base_dir'=>__DIR__ . '/../language',
                'pattern'=>'%s.mo',
           ),
        )
    ),
    'controllers'=>array(
        'invokables'=>array(
            'Application\Controller\Cron'=>'Application\Controller\Cron',
        ),
    ),
    'console'=>array(
        'router'=>array(
            'routes'=>array(
                'cron-run'=>array(
                    'options'=>array(
                        'route'=>'cron run <job>',
                        'defaults'=>array(
                            'controller'=>'Application\Controller\Cron',
                            'action'=>'run'
                        )
                    )
                )
            )
        )
    )
);
