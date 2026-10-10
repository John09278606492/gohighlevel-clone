<?php

use App\Http\Controllers\PublicController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/app');

Route::get('/book/{subAccount:slug}/{calendar}', [PublicController::class, 'booking'])->name('booking.show');
Route::get('/p/{subAccount:slug}/{slug?}', [PublicController::class, 'page'])->name('pages.show');
