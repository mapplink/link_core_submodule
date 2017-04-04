<?php
return array(
    'BjyAuthorize\Guard\Controller'=>array(
        // Guest
        array('controller'=>'zfcuser', 'roles'=>array('guest')),
        array('controller'=>'Web\Controller\CRUD\ZfcUser', 'roles'=>array('guest')),
        // Picker Packer
        array('controller'=>'Web\Controller\Default', 'action'=>'index', 'roles'=>array('picker-packer')),
        // Manager
        array('controller'=>'Web\Controller\Default', 'action'=>'dashboard', 'roles'=>array('manager')),
        array('controller'=>'Web\Controller\CRUD\UserAdmin', 'roles'=>array('manager')),
        array('controller'=>'Web\Controller\CRUD\LocationAdmin', 'roles'=>array('manager')),
        array('controller'=>'Web\Controller\CRUD\EmailTemplateAdmin', 'roles'=>array('manager')),
        array('controller'=>'Web\Controller\CRUD\EmailTemplateParamAdmin', 'roles'=>array('manager')),
        array('controller'=>'Web\Controller\CRUD\LogEntryAdmin', 'roles'=>array('manager')),
        array('controller'=>'Web\Controller\CRUD\EmailLogAdmin', 'roles'=>array('manager')),
        array('controller'=>'Web\Controller\CRUD\AuditLogAdmin', 'roles'=>array('manager')),
        // Admin
        array('controller'=>'Web\Controller\Entity\GenericEntity', 'roles'=>array('administrator')),
        array('controller'=>'Web\Controller\CRUD\EmailSenderAdmin', 'roles'=>array('administrator')),
        array('controller'=>'Web\Controller\CRUD\ConfigAdmin', 'roles'=>array('administrator')),
        array('controller'=>'Web\Controller\CRUD\CronjobAdmin', 'roles'=>array('administrator')),
        array('controller'=>'Web\Controller\CRUD\NodeAdmin', 'roles'=>array('administrator')),
        array('controller'=>'Web\Controller\Query', 'roles'=>array('administrator')),
    )
);
