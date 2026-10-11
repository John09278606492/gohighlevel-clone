<?php

use App\Http\Controllers\Public\ChatWidgetController;
use App\Http\Controllers\Public\DocumentController;
use App\Http\Controllers\Public\InvoiceController;
use App\Http\Controllers\Public\PortalController;
use App\Http\Controllers\Public\ReviewController;
use App\Http\Controllers\Public\TrackingController;
use App\Http\Controllers\Public\WebhookController;
use App\Http\Controllers\PublicController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/app');

// Booking calendars and funnel / website pages
Route::get('/book/{subAccount:slug}/{calendar}', [PublicController::class, 'booking'])->name('booking.show');
Route::get('/p/{subAccount:slug}/{slug?}', [PublicController::class, 'page'])->name('pages.show');

// Trigger links, campaign tracking and unsubscribe
Route::get('/l/{code}', [TrackingController::class, 'link'])->name('links.go');
Route::get('/t/o/{token}.gif', [TrackingController::class, 'open'])->name('tracking.open');
Route::get('/t/c/{token}', [TrackingController::class, 'click'])->name('tracking.click');
Route::get('/u/{token}', [TrackingController::class, 'unsubscribe'])->name('unsubscribe');
Route::post('/u/{token}', [TrackingController::class, 'confirmUnsubscribe'])->middleware('throttle:public-forms')->name('unsubscribe.confirm');

// Invoices and payments
Route::get('/invoice/{token}', [InvoiceController::class, 'show'])->name('invoices.public');
Route::post('/invoice/{token}/pay', [InvoiceController::class, 'pay'])->middleware('throttle:public-forms')->name('invoices.pay');
Route::get('/invoice/{token}/paid', [InvoiceController::class, 'paid'])->name('invoices.paid');

// Reputation
Route::get('/review/{token}', [ReviewController::class, 'show'])->name('reviews.public');
Route::post('/review/{token}', [ReviewController::class, 'store'])->middleware('throttle:public-forms')->name('reviews.submit');
Route::get('/reviews/{subAccount:slug}', [ReviewController::class, 'showGeneric'])->name('reviews.generic');
Route::post('/reviews/{subAccount:slug}', [ReviewController::class, 'store'])->middleware('throttle:public-forms')->name('reviews.generic.submit');

// Proposals and contracts
Route::get('/doc/{token}', [DocumentController::class, 'show'])->name('documents.public');
Route::post('/doc/{token}', [DocumentController::class, 'sign'])->middleware('throttle:public-forms')->name('documents.sign');

// Member portal (courses)
Route::get('/portal/{token}', [PortalController::class, 'course'])->name('portal.course');
Route::get('/portal/{token}/lessons/{lesson}', [PortalController::class, 'lesson'])->name('portal.lesson');
Route::post('/portal/{token}/lessons/{lesson}/complete', [PortalController::class, 'complete'])->name('portal.complete');

// Website chat widget (cross-origin, so no CSRF; see bootstrap/app.php)
Route::get('/widget/{subAccount:slug}.js', [ChatWidgetController::class, 'script'])->name('widget.script');
Route::post('/widget/{subAccount:slug}/messages', [ChatWidgetController::class, 'send'])->middleware('throttle:public-forms')->name('widget.send');
Route::get('/widget/{subAccount:slug}/messages', [ChatWidgetController::class, 'poll'])->middleware('throttle:120,1')->name('widget.poll');

// Provider webhooks
Route::prefix('webhooks')->middleware('throttle:300,1')->group(function () {
    Route::post('/twilio/{subAccount:slug}/sms', [WebhookController::class, 'twilioSms'])->name('webhooks.twilio.sms');
    Route::post('/twilio/{subAccount:slug}/status', [WebhookController::class, 'twilioStatus'])->name('webhooks.twilio.status');
    Route::post('/email/{subAccount:slug}/{secret}', [WebhookController::class, 'inboundEmail'])->name('webhooks.email');
    Route::post('/stripe/{subAccount:slug}', [InvoiceController::class, 'stripeWebhook'])->name('webhooks.stripe');
});
