<?php

use Illuminate\Support\Facades\Route;

/*
| The Vue SPA owns every non-API URL. `password.reset` only exists so the
| reset email can build a link to the SPA's reset screen.
*/

Route::view('/reset-password/{token}', 'app')->name('password.reset');

Route::view('/{any?}', 'app')->where('any', '^(?!api|sanctum|up|build|storage).*$');
