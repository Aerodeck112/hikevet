<?php

use App\Http\Controllers\Account\AuthController;
use App\Http\Controllers\Account\DashboardController;
use App\Http\Controllers\Booking\BookingController;
use App\Http\Controllers\Site\ContactController;
use App\Http\Controllers\Site\SitemapController;
use App\Http\Controllers\Subscriptions\ConfiguratorController;
use App\Http\Controllers\Tablet\ConsentTabletController;
use Illuminate\Support\Facades\Route;

Route::get('/sitemap.xml', [SitemapController::class, 'sitemap']);
Route::get('/robots.txt', [SitemapController::class, 'robots']);

Route::post('/hikevet-contact', [ContactController::class, 'store'])->middleware('throttle:forms');

// Abonamente: oferta si contractul se calculeaza si se salveaza pe server.
Route::post('/abonamente/oferta', [ConfiguratorController::class, 'offer'])->middleware('throttle:30,1');
Route::post('/abonamente/contract', [ConfiguratorController::class, 'submit'])->middleware('throttle:contract');

// Cont client (URL-urile din site-ul vechi: /acces-cont/ si /panou-cont/).
Route::get('/acces-cont', [AuthController::class, 'access'])->name('login');
Route::post('/acces-cont/autentificare', [AuthController::class, 'login'])->middleware('throttle:login');
Route::post('/acces-cont/inregistrare', [AuthController::class, 'register'])->middleware('throttle:forms');
Route::post('/acces-cont/iesire', [AuthController::class, 'logout'])->middleware('auth')->name('logout');
Route::get('/acces-cont/parola-uitata', [AuthController::class, 'forgot'])->name('password.request');
Route::post('/acces-cont/parola-uitata', [AuthController::class, 'sendReset'])->middleware('throttle:forms')->name('password.email');
Route::get('/acces-cont/parola-noua/{token}', [AuthController::class, 'reset'])->name('password.reset');
Route::post('/acces-cont/parola-noua', [AuthController::class, 'updatePassword'])->middleware('throttle:forms')->name('password.update');

Route::middleware('auth')->group(function () {
    Route::get('/panou-cont', [DashboardController::class, 'index'])->name('account');
    Route::get('/panou-cont/contracte/{contract}/pdf', [DashboardController::class, 'contractPdf'])->name('account.contract.pdf');
    Route::post('/panou-cont/wallet/solicitare', [DashboardController::class, 'requestTopup'])->middleware('throttle:forms');
    Route::post('/panou-cont/profil', [DashboardController::class, 'updateProfile'])->middleware('throttle:forms');
    Route::post('/panou-cont/retrimite-activare', [DashboardController::class, 'resendActivation'])->middleware('throttle:forms');
});

// Consimtamant pe tableta: pagina fixa + cod de 6 cifre.
Route::get('/consimtamant-tableta', [ConsentTabletController::class, 'index'])->name('tablet');
Route::post('/consimtamant-tableta/cod', [ConsentTabletController::class, 'open'])->middleware('throttle:consent-code');
Route::get('/consimtamant-tableta/document', [ConsentTabletController::class, 'document'])->name('tablet.document');
Route::post('/consimtamant-tableta/semnare', [ConsentTabletController::class, 'sign'])->middleware('throttle:forms');
Route::post('/consimtamant-tableta/inchide', [ConsentTabletController::class, 'close']);

// Programari proprii. Publice doar cand modul de programare este "internal" (vezi App\Support\Booking).
Route::get('/programare', [BookingController::class, 'create'])->name('booking');
Route::get('/programare/intervale', [BookingController::class, 'slots'])->middleware('throttle:60,1');
Route::post('/programare', [BookingController::class, 'store'])->middleware('throttle:booking');
Route::get('/programare/confirmare/{token}', [BookingController::class, 'show'])->name('booking.show');
Route::post('/programare/anulare/{token}', [BookingController::class, 'cancel'])->middleware('throttle:forms');
