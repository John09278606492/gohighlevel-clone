<?php

use App\Http\Controllers\Api\ContactController;
use App\Http\Controllers\Api\SalesController;
use App\Http\Middleware\AuthenticateApiKey;
use Illuminate\Support\Facades\Route;

/*
| REST API v1. Authenticate with a sub-account API key (Settings → API keys):
|   Authorization: Bearer le_xxxxxxxx
| Every request only sees that sub-account's data. See docs/API.md.
*/
Route::prefix('v1')->middleware([AuthenticateApiKey::class, 'throttle:api'])->group(function () {
    Route::get('contacts', [ContactController::class, 'index']);
    Route::post('contacts', [ContactController::class, 'store']);
    Route::get('contacts/{id}', [ContactController::class, 'show'])->whereNumber('id');
    Route::match(['put', 'patch'], 'contacts/{id}', [ContactController::class, 'update'])->whereNumber('id');
    Route::delete('contacts/{id}', [ContactController::class, 'destroy'])->whereNumber('id');
    Route::post('contacts/{id}/tags', [ContactController::class, 'addTags'])->whereNumber('id');
    Route::delete('contacts/{id}/tags', [ContactController::class, 'removeTags'])->whereNumber('id');
    Route::post('contacts/{id}/notes', [ContactController::class, 'addNote'])->whereNumber('id');
    Route::post('contacts/{id}/messages', [ContactController::class, 'sendMessage'])->whereNumber('id');

    Route::get('pipelines', [SalesController::class, 'pipelines']);
    Route::get('opportunities', [SalesController::class, 'opportunities']);
    Route::post('opportunities', [SalesController::class, 'storeOpportunity']);
    Route::match(['put', 'patch'], 'opportunities/{id}', [SalesController::class, 'updateOpportunity'])->whereNumber('id');

    Route::get('calendars', [SalesController::class, 'calendars']);
    Route::get('appointments', [SalesController::class, 'appointments']);
    Route::post('appointments', [SalesController::class, 'storeAppointment']);
    Route::match(['put', 'patch'], 'appointments/{id}', [SalesController::class, 'updateAppointment'])->whereNumber('id');

    Route::get('invoices', [SalesController::class, 'invoices']);
});
