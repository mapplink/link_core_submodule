<?php
/**
 * @category Email
 * @author Seo Yao
 * @author Andreas Gerhards <andreas@lero9.co.nz>
 * @copyright Copyright (c) 2014 LERO9 Ltd.
 * @license http://opensource.org/licenses/BSD-3-Clause BSD-3-Clause - Please view LICENSE.md for more information
 */

return array (
    'doctrine'=>array(
        'driver'=>array(
            'email_entities'=>array(
                'class'=>'Doctrine\ORM\Mapping\Driver\AnnotationDriver',
                'cache'=>'array',
                'paths'=>array(__DIR__.'/../src/Email/Entity')
            ),
            'orm_default'=>array(
                'drivers'=>array(
                    'Email\Entity'=>'email_entities'
                )
            )
        )
    ),
    'service_manager'=>array(
        'factories' => array(
            'Email\Service\MailService'=>'Email\Service\MailService',
        )
    ),
);
