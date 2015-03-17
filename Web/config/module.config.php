<?php

$config = array (
    'service_manager' => array(
        'invokables'=>array(
            'widget_entitytype'=>'Web\Widget\EntityTypeWidget',
            'widget_todaysupdates'=>'Web\Widget\TodaysUpdatesWidget',
            'widget_ordersstatus'=>'Web\Widget\OrdersStatus',
            'widget_ordersshippingmethod'=>'Web\Widget\OrdersShippingMethod',
        ),
         'factories' => array(
             'navigation' => 'Zend\Navigation\Service\DefaultNavigationFactory', // <-- add this
         ),
     ),
	
    'controllers' => array(
        'invokables' => array(
            'Web\Controller\Default' => 'Web\Controller\DefaultController',
            'Web\Controller\Query' => 'Web\Controller\QueryController',
            'Web\Controller\CRUD\UserAdmin' => 'Web\Controller\CRUD\UserAdminController',
            'Web\Controller\CRUD\ZfcUser' => 'Web\Controller\CRUD\ZfcUserController',
            'Web\Controller\CRUD\LocationAdmin' => 'Web\Controller\CRUD\LocationAdminController',
            'Web\Controller\CRUD\EmailTemplateAdmin' => 'Web\Controller\CRUD\EmailTemplateAdminController',
            'Web\Controller\CRUD\ConfigAdmin' => 'Web\Controller\CRUD\ConfigAdminController',
            'Web\Controller\CRUD\CronjobAdmin' => 'Web\Controller\CRUD\CronjobAdminController',
            'Web\Controller\CRUD\LogEntryAdmin' => 'Web\Controller\CRUD\LogEntryAdminController',
            'Web\Controller\CRUD\EmailLogAdmin' => 'Web\Controller\CRUD\EmailLogAdminController',
            'Web\Controller\CRUD\AuditLogAdmin' => 'Web\Controller\CRUD\AuditLogAdminController',
            'Web\Controller\CRUD\NodeAdmin' => 'Web\Controller\CRUD\NodeAdminController',
            'Web\Controller\CRUD\EmailTemplateParamAdmin' => 'Web\Controller\CRUD\EmailTemplateParamAdminController',
            'Web\Controller\Entity\GenericEntity' => 'Web\Controller\Entity\GenericEntityController',
        ),
    ),

    'view_manager' => array(
        'display_not_found_reason' => true,
        'display_exceptions' => true,
        'doctype' => 'HTML5',
        'not_found_template' => 'error/404',
        'exception_template' => 'error/index',
        'template_map' => array(
            'layout/layout' => __DIR__ . '/../view/layout/layout.phtml',
            'error/404' => __DIR__ . '/../view/error/404.phtml',
            'error/index' => __DIR__ . '/../view/error/index.phtml',
        ),
        'template_path_stack' => array(
            __DIR__ . '/../view',
        ),
        'strategies' => array(
           'ViewJsonStrategy',
        ),
    ),

    'dashboard_widgets'=>array(
        'ordersstatus'=>array(
            'column'=>1,
            'exclude'=>array(
                'complete',
                'closed',
                'canceled'
            )
        ),
        'ordersshippingmethod'=>array(
            'column'=>1,
            'exclude'=>false,
        ),
        'entitytype'=>array(
            'column'=>1,
            'exclude'=>array(
                'orderitem',
                'creditmemoitem',
                'stockitem',
                'address',
            ),
        ),
        'todaysupdates'=>array(
            'column'=>1,
            'exclude_type'=>array(
                'orderitem',
                'creditmemoitem',
                'stockitem',
                'address',
            ),
        ),
    ),

    'zfcuser' => array(
        // Telling ZfcUser to use our own class
        'user_entity_class' => 'Magelink\Entity\User',

        // Telling ZfcUserDoctrineORM to skip the entities it defines
        'enable_default_entities' => false,

        // Landing page after login
        'login_redirect_route' => 'home',

        // Enables username field on the registration form, and allows users to log in using their username OR email address.
        'enable_username' => true,

        // Specify the allowable identity modes, in the order they should be checked by the Authentication plugin
        'auth_identity_fields' => array( 'email', 'username' ),
    ),

    'bjyauthorize' => array(
        // Using the authentication identity provider, which basically reads the roles from the auth service's identity
        'identity_provider' => 'BjyAuthorize\Provider\Identity\AuthenticationIdentityProvider',

        'role_providers' => array(
            // using an object repository (entity repository) to load all roles into our ACL
            'BjyAuthorize\Provider\Role\ObjectRepositoryProvider' => array(
                'object_manager' => 'Doctrine\ORM\EntityManager',
                'role_entity_class' => 'Magelink\Entity\UserRole',
            ),
        ),

        // strategy service name for the strategy listener to be used when permission-related errors are detected
        'unauthorized_strategy' => 'Magelink\Auth\UnauthorizedStrategy',

        'guards' => include __DIR__ . '/module.acl.config.php',
    ),
);

return array_merge(
    $config,
    include __DIR__ . '/module.navigation.config.php',
    include __DIR__ . '/module.router.config.php'
);
