<?php
return [
    'mysql'  => [
        'driver'      => 'mysql',
        'host'        => 'localhost',
        'port'        => '3306',
        'database'    => 'laravel_ofisuite',
        'username'    => 'root',
        'password'    => '',
        'charset'     => 'utf8',
        'encoding'    => 'utf8_general_ci',
        'prefix'      => '',
        'strict'      => true,
        'engine'      => null,
    ],

    'mysql_nas'  => [
        'driver'      => 'mysql',
        'host'        => '192.168.1.168',
        'port'        => '3306',
        'database'    => 'downloads',
        'username'    => 'root',
        'password'    => '',
        'charset'     => 'utf8',
        'encoding'    => 'utf8_general_ci',
        'prefix'      => '',
        'strict'      => true,
        'engine'      => null,
    ],

    'mysql_nas2'  => [
        'driver'      => 'mysql',
        'host'        => '192.168.1.168',
        'port'        => '3306',
        'database'    => 'lifoly',
        'username'    => 'root',
        'password'    => '',
        'charset'     => 'utf8',
        'encoding'    => 'utf8_general_ci',
        'prefix'      => '',
        'strict'      => true,
        'engine'      => null,
    ],

    'mysql_rw'  => [
        'read' => [
            'driver'      => 'mysql',
            'host'        => 'localhost',
            'port'        => '3306',
            'database'    => 'laravel_ofisuite',
            'username'    => 'root',
            'password'    => '',
            'charset'     => 'utf8',
            'encoding'    => 'utf8_general_ci',
            'prefix'      => '',
            'strict'      => true,
            'engine'      => null,
        ],
        
        'write' => [
            'driver'      => 'mysql',
            'host'        => 'localhost',
            'port'        => '3306',
            'database'    => 'laravel_ofisuite',
            'username'    => 'root',
            'password'    => '',
            'charset'     => 'utf8',
            'encoding'    => 'utf8_general_ci',
            'prefix'      => '',
            'strict'      => true,
            'engine'      => null,
        ]
    ],

    'mysql2'  => [
        'driver'      => 'mysql',
        'host'        => 'localhost',
        'port'        => '3306',
        'database'    => 'mysql',
        'username'    => 'root',
        'password'    => '',
        'charset'     => 'utf8',
        'encoding'    => 'utf8_general_ci',
        'prefix'      => '',
        'strict'      => true,
        'engine'      => null,
    ],

    'pgsql'  => [
        'driver'   => 'pgsql',
        'host'     => '127.0.0.1',
        'port'     => '5432',
        'database' => 'forge',
        'username' => 'forge',
        'password' => '',
        'charset'  => 'utf8',
        'prefix'   => '',
        'schema'   => 'public',
        'sslmode'  => 'prefer',
    ],

    'sqlsrv' => [
        'driver'   => 'sqlsrv',
        'host'     => 'localhost',
        'port'     => '1433',
        'database' => 'forge',
        'username' => 'forge',
        'password' => '',
        'charset'  => 'utf8',
        'prefix'   => '',
    ],

    'redis'  => [

        'client'  => 'predis',

        'default' => [
            'host'     => '127.0.0.1',
            'password' => null,
            'port'     => 6379,
            'database' => 0,
        ],

    ],

];
