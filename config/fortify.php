<?php

use Laravel\Fortify\Features;

return [

    'guard' => 'web',

    'passwords' => 'users',

    /*
    | Login por NOME DE USUÁRIO (não e-mail). Sistema de admin único.
    */
    'username' => 'username',

    'email' => 'email',

    'lowercase_usernames' => true,

    'home' => '/',

    'prefix' => '',

    'domain' => null,

    'middleware' => ['web'],

    'limiters' => [
        'login' => 'login',
        'two-factor' => 'two-factor',
    ],

    'views' => true,

    'features' => [
        // Sistema privado de admin único: registro público e reset por e-mail
        // ficam DESLIGADOS de propósito (não há SMTP e não queremos signup aberto).
        // Features::registration(),
        // Features::resetPasswords(),
        // Features::emailVerification(),
        Features::updateProfileInformation(),
        Features::updatePasswords(),
        // 2FA/passkeys: disponível no Fortify 13, ligar depois se desejado.
    ],

];
