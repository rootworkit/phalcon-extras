<?php

namespace App\Config;

use Phalcon\Config;
use Phalcon\Acl;

return new Config([
    'defaultRole' => 'Guest',
    'defaultAction' => Acl::DENY,
    'roles' => [
        'Guest',
        'User',
        'Admin',
    ],
    'inheritance' => [ // Inheritance is optional
        'User'  => 'Guest',
        'Admin' => 'User',
    ],
    'resources' => [ // Role => [Controller => [action1, action2]]
        'Guest' => [
            'Index'     => ['index'],
        ],
        'User'  => [
            'Account'   => ['create', 'read', 'update'],
        ],
        'Admin' => [
            'Account'   => ['delete', 'index'],
            'Orders'    => ['create', 'delete', 'index', 'read', 'update'],
        ],
    ],
]);
