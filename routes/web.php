<?php

use App\Http\Controllers\BookController;
use App\Http\Controllers\DesignController;
use App\Http\Controllers\LessonController;
use App\Http\Controllers\PortfolioController;
use App\Http\Controllers\StoreController;
use App\Models\Book;
use App\Models\Design;
use App\Models\Lesson;
use App\Models\StoreItem;
use Illuminate\Support\Facades\Route;

/*
| The Vue SPA owns every non-API URL. `password.reset` only exists so the
| reset email can build a link to the SPA's reset screen.
*/

Route::view('/reset-password/{token}', 'app')->name('password.reset');

Route::get('/media/'.Lesson::IMAGE_DIRECTORY.'/{file}', [LessonController::class, 'image'])->where('file', '[A-Za-z0-9_-]+\.(?:jpg|jpeg|png|webp)');
Route::get('/media/'.StoreItem::IMAGE_DIRECTORY.'/{file}', [StoreController::class, 'image'])->where('file', '[A-Za-z0-9_-]+\.(?:jpg|jpeg|png|webp)');
Route::get('/media/'.Book::COVER_DIRECTORY.'/{file}', [BookController::class, 'cover'])->where('file', '[A-Za-z0-9_-]+\.(?:jpg|jpeg|png|webp)');
Route::get('/media/'.Design::IMAGE_DIRECTORY.'/{file}', [DesignController::class, 'background'])->where('file', '[A-Za-z0-9_-]+\.(?:jpg|jpeg|png|webp)');
Route::get('/media/{directory}/{file}', [PortfolioController::class, 'media'])->where('directory', 'vision|portfolio|days')->where('file', '[A-Za-z0-9_-]+\.(?:jpg|jpeg|png|webp)');

Route::view('/{any?}', 'app')->where('any', '^(?!api|sanctum|up|build|storage|media).*$');
