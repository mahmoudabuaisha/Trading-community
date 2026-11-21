<?php

return [

    /*
    | First administrator account, created by `php artisan db:seed`.
    | Read through config (not env()) so it also works with `config:cache`.
    */
    'admin' => [
        'name' => env('ADMIN_NAME'),
        'email' => env('ADMIN_EMAIL'),
        'password' => env('ADMIN_PASSWORD'),
    ],

];
