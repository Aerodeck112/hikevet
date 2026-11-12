<?php

use App\Http\Controllers\Site\PageController;
use Illuminate\Support\Facades\Route;

Route::get('/', [PageController::class, 'home'])->name('home');

require __DIR__.'/site.php';

// Ultima ruta: servicii, articole si pagini, cu slug-urile de prim nivel din WordPress.
Route::get('/{slug}', [PageController::class, 'show'])->where('slug', '[a-z0-9\-]+')->name('page');
