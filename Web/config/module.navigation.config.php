<?php

$navigation = array(
    'navigation'=>array(
        'default'=>array(
            'dashboard'=>array(
                'label'=>'Dashboard',
                'route'=>'home',
                'iconClass'=>'glyphicon glyphicon-dashboard',
                'pages'=>array(
                ),
            ),
            'entities'=>array(
                'label'=>'Entities',
                'uri'=>'/entities',
                'iconClass'=>'glyphicon glyphicon-th',
                'pages'=>array(
                    array(
                        'label'=>'Customers',
                        'uri'=>'/entities/grid/customer',
                    ),
                    array(
                        'label'=>'Address',
                        'uri'=>'/entities/grid/address',
                    ),
                    array(
                        'label'=>'Product',
                        'uri'=>'/entities/grid/product',
                    ),
                    array(
                        'label'=>'Stockitem',
                        'uri'=>'/entities/grid/stockitem',
                    ),
                    array(
                        'label'=>'Order',
                        'uri'=>'/entities/grid/order',
                    ),
                    array(
                        'label'=>'Order Item',
                        'uri'=>'/entities/grid/orderitem',
                    ),
                    array(
                        'label'=>'Credit Memo',
                        'uri'=>'/entities/grid/creditmemo',
                    ),
                    array(
                        'label'=>'Credit Memo Item',
                        'uri'=>'/entities/grid/creditmemoitem',
                    ),
                )
            ),
            'orders'=>array(),
            'picklist'=>array(),
            'pick'=>array(),
            'pack'=>array(),
            'admin'=>array(
                'label'=>'Admin',
                'route'=>'user-admin/list',
                'iconClass'=>'glyphicon glyphicon-user',
                'pages'=>array(
                    array(
                        'label'=>'Product Location',
                        'route'=>'location-admin/list',
                    ),
                    array(
                        'label'=>'Default Email Sender',
                        'route'=>'email-sender-admin/list',
                    ),
                    array(
                        'label'=>'Email Template',
                        'route'=>'email-template-admin/list',
                    ),
                    array(
                        'label'=>'Email Params',
                        'route'=>'email-template-param-admin/list',
                    )
                )
            ),
            'system'=>array(
                'label'=>'System',
                'route'=>'config-admin/list',
                'iconClass'=>'glyphicon glyphicon-cog',
                'pages'=>array(
                    array(
                        'label'=>'Cronjob Monitor',
                        'route'=>'cronjob-admin/list'
                    ),
                    array(
                        'label'=>'Node Config',
                        'route'=>'node-admin/list',
                    ),
                    array(
                        'label'=>'General Log',
                        'route'=>'log-entry-admin/list',
                    ),
                    array(
                        'label'=>'Audit Log',
                        'route'=>'audit-log-admin/list',
                    ),
                    array(
                        'label'=>'MLQL Interface',
                        'route'=>'query',
                    )
                )
            )
        )
    )
);

$hopsNavigation = str_replace('magelink/Web', 'module/HOPS', __FILE__);
if (file_exists($hopsNavigation)) {
    $navigation = array_replace_recursive(
        $navigation,
        include $hopsNavigation
    );
}

foreach ($navigation['navigation']['default'] as $label=>$data) {
    $isPlaceholder = !is_array($data) || count($data) == 0;
    if ($isPlaceholder) {
        unset($navigation['navigation']['default'][$label]);
    }
}

return $navigation;
