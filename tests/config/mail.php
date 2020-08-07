<?php
return [
    'driver' => 'smtp',
    'charset'  => 'utf8',
    
    'smtp' => [
        'hostname' => 'localhost',
        'username' => 'test@test.dev',
        'password' => null,
        'port'     => 1025,
        'tls'      => false,
        'ssl'      => false,
        'timeout'  => 50,
    ],

    'mail' => [
        'default' => 'contact',
        'contact' => [
            'address' => app_env('CONTACT_EMAIL'),
            'username' => app_env('CONTACT_NAME')
        ],
        'info' => [
            'address' => 'info@exemple.com',
            'username' => 'Address d\'Information'
        ]
    ]
];
