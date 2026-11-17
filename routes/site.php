<?php

use App\Http\Controllers\Account\AuthController;
use App\Http\Controllers\Account\DashboardController;
use App\Http\Controllers\Account\PaymentController;
use App\Http\Controllers\Admin\CampaignAppointmentController;
use App\Http\Controllers\Admin\CampaignController;
use App\Http\Controllers\Admin\CampaignReportController;
use App\Http\Controllers\Admin\DocumentController;
use App\Http\Controllers\Booking\BookingController;
use App\Http\Controllers\Payments\StripeWebhookController;
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
    Route::post('/panou-cont/plata/wallet', [PaymentController::class, 'wallet'])->middleware('throttle:forms')->name('account.pay.wallet');
    Route::post('/panou-cont/plata/abonament/{contract}', [PaymentController::class, 'subscription'])->middleware('throttle:forms')->name('account.pay.subscription');
    Route::get('/panou-cont/plata/{payment}/confirmare', [PaymentController::class, 'confirm'])->name('account.pay.confirm');
    Route::get('/panou-cont/plata/{payment}/renuntare', [PaymentController::class, 'cancel'])->name('account.pay.cancel');
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

// Documente servite personalului din admin (autorizare pe permisiune + jurnal de acces).
Route::middleware('auth')->prefix('admin/documents')->group(function () {
    Route::get('/contracts/{contract}/pdf', [DocumentController::class, 'contractPdf'])->name('admin.contracts.pdf');
    Route::get('/consents/{consent}/print', [DocumentController::class, 'consentPrint'])->name('admin.consents.print');
});

// Notificarile Stripe pentru plata online (fara CSRF; semnatura se verifica in controller).
Route::post('/stripe/webhook', StripeWebhookController::class)->middleware('throttle:120,1')->name('stripe.webhook');

// Campania de sterilizare: actiuni din ecranele de administrare (permisiunile se verifica in controller).
Route::middleware('auth')->prefix('admin/campanie')->name('admin.campaign.')->controller(CampaignController::class)->group(function () {
    Route::post('/dosar', 'save')->name('save');
    Route::post('/dosar/{case}/animal', 'addVisitPet')->name('visit-pet');
    Route::post('/dosar/{case}/scoate-din-vizita', 'removeVisitPet')->name('visit-pet.remove');
    Route::post('/dosar/{case}/sterge', 'delete')->name('delete');
    Route::post('/dosar/{case}/email-final', 'resendFinal')->name('resend-final');
    Route::post('/dosar/{case}/follow-up', 'sendFollowup')->name('followup');
    Route::post('/dosar/{case}/cod-tableta', 'consentCode')->name('consent-code');
    Route::get('/dosar/{case}/foto-status', 'photoStatus')->name('photo-status');
    Route::get('/document/{document}', 'document')->name('document');
});

// Raportul pentru Primarie: exporturi si control (permisiunile se verifica in controller).
Route::middleware('auth')->prefix('admin/campanie/raport')->name('admin.campaign.report.')->controller(CampaignReportController::class)->group(function () {
    Route::get('/excel', 'xlsx')->name('xlsx');
    Route::get('/pdf', 'pdf')->name('pdf');
    Route::get('/centralizator', 'centralizator')->name('centralizator');
    Route::get('/csv', 'csv')->name('csv');
    Route::post('/zip', 'zip')->name('zip');
    Route::post('/dosar/{case}/zip', 'caseZip')->name('case-zip');
    Route::post('/dosar/{case}/foto-verificata', 'markPhoto')->name('mark-photo');
    Route::post('/dosar/{case}/consimtamant-corectat', 'reissueConsent')->name('reissue-consent');
    Route::post('/foto-verificate', 'bulkPhoto')->name('bulk-photo');
    Route::post('/aliniaza-dupa-foto', 'align')->name('align');
    Route::get('/registru.csv', 'registryCsv')->name('registry-csv');
    Route::post('/reconciliere', 'saveReconciliation')->name('reconciliation');
    Route::get('/reconciliere.csv', 'reconciliationCsv')->name('reconciliation-csv');
    Route::get('/reconciliere/{month}.pdf', 'reconciliationPdf')->where('month', '2026-0[789]')->name('reconciliation-pdf');
    Route::post('/raportate-anterior', 'savePriorReports')->name('prior');
    Route::post('/validare-in-masa', 'bulkValidate')->name('bulk-validate');
    Route::post('/reparare', 'repair')->name('repair');
});

Route::middleware('auth')->prefix('admin/campanie/programari')->name('admin.campaign.appointments.')->controller(CampaignAppointmentController::class)->group(function () {
    Route::post('/', 'store')->name('store');
    Route::get('/disponibilitate', 'availability')->name('availability');
    Route::post('/{appointment}', 'update')->name('update');
    Route::post('/{appointment}/anuleaza', 'cancel')->name('cancel');
    Route::post('/{appointment}/sterge', 'destroy')->name('destroy');
    Route::post('/{appointment}/email', 'email')->name('email');
    Route::post('/{appointment}/consultatie', 'startConsultation')->name('consultation');
});

// Incarcarea fotografiei de pe telefon: acces doar cu tokenul aleatoriu al dosarului.
Route::get('/campanie/foto/{token}', [CampaignController::class, 'phonePage'])->where('token', '[A-Za-z0-9]{20,64}')->name('campaign.phone');
Route::post('/campanie/foto/{token}', [CampaignController::class, 'phoneUpload'])->where('token', '[A-Za-z0-9]{20,64}')->middleware('throttle:20,1')->name('campaign.phone.upload');
