<?php

return array(
    'navigation'=>array(
        'default'=>array(
            array(
                'label'=>'Dashboard',
                'route'=>'home',
                'iconClass'=>'glyphicon glyphicon-dashboard',
                'pages'=>array(
                    
                ),
            ),

            array(
                'label'=>'Entities',
                'uri'=>'/entities',
                'iconClass'=>'glyphicon glyphicon-th',
                'pages'=>array(
                    array(
                        'label'=>'Customers',
                        'uri'  =>'/entities/grid/customer',
                    ),
                    array(
                        'label'=>'Address',
                        'uri'  =>'/entities/grid/address',
                    ),
                    array(
                        'label'=>'Product',
                        'uri'  =>'/entities/grid/product',
                    ),
                    array(
                        'label'=>'Stockitem',
                        'uri'  =>'/entities/grid/stockitem',
                    ),
                    array(
                        'label'=>'Order',
                        'uri'  =>'/entities/grid/order',
                    ),
                    array(
                        'label'=>'Order Item',
                        'uri'  =>'/entities/grid/orderitem',
                    ),
                    array(
                        'label'=>'Credit Memo',
                        'uri'  =>'/entities/grid/creditmemo',
                    ),
                    array(
                        'label'=>'Credit Memo Item',
                        'uri'  =>'/entities/grid/creditmemoitem',
                    ),
                )
            ),

            array(
                'label'=>'Orders',
                'uri'=>'/hops_order',
                'iconClass'=>'glyphicon glyphicon-shopping-cart',
                'pages'=>array(
                    array(
                        'label'=>'Pending',
                        'uri'  =>'/hops_order/showpending',
                    ),
                    array(
                        'label'=>'Pending On Account',
                        'uri'  =>'/hops_order/showonaccountpending',
                    ),
                    array(
                        'label'=>'New',
                        'uri'  =>'/hops_order/shownew',
                    ),
                    array(
                        'label'=>'Held',
                        'uri'  =>'/hops_order/showheld',
                    ),
/* <<<HopsSpecific */ 
                    array(
                        'label'=>'Queued',
                        'uri'  =>'/hops_order/showqueued',
                    ),
                    array(
                        'label'=>'To Pick',
                        'uri'  =>'/hops_order/showpicking',
                    ),
                    array(
                        'label'=>'To Print',
                        'uri'  =>'/hops_order/showpackingnew',
                    ),
                    array(
                        'label'=>'To Pack',
                        'uri'  =>'/hops_order/showpackingprinted',
                    ),
                    array(
                        'label'=>'Awaiting Stock',
                        'uri'  =>'/hops_order/showawaitingstock',
                    ),
/* HopsSpecific; */
                    array(
                        'label'=>'Flagged',
                        'uri'  =>'/hops_order/showflagged',
                    )
                )
            ),
/* <<<HopsSpecific */ 
            array(
                'label'    =>'Picklist',
                'route'      =>'hops_picklist',
                'iconClass'=>'glyphicon glyphicon-list',
            ),

            array(
                'label'=>'Pick',
                'route'=>'picklist-scanning/start',
                'iconClass'=>'glyphicon glyphicon-edit',
            ),

            array(
                'label'=>'Pack',
                'route'=>'packing/start',
                'iconClass'=>'glyphicon glyphicon-gift',
            ),
/* HopsSpecific; */

            array(
                'label'=>'Admin',
                'route'=>'user-admin/list',
                'iconClass'=>'glyphicon glyphicon-user',
                'pages'=>array(
                    array(
                        'label'=>'Product Location',
                        'route'=>'location-admin/list',
                    ),
                    array(
                        'label'=>'Email Template',
                        'route'=>'email-template-admin/list',
                    ),
                    array(
                        'label'=>'Email Params',
                        'route'=>'email-template-param-admin/list',
                    ),
/* <<<HopsSpecific */
                    array(
                        'label'=>'Scanner Location',
                        'route'=>'hops_scannerlocation',
                    ),

                    array(
                        'label'=>'Pigeon Hole',
                        'route'=>'hops_pigeonhole',
                    )
/* HopsSpecific; */
                )
            ),

            array(
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
