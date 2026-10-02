<?php

use App\Http\Controllers\System\MeetingRoomBookingController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth'])
    ->prefix('booking-phong-hop')
    ->name('meeting-room-bookings.')
    ->controller(MeetingRoomBookingController::class)
    ->group(function () {
        Route::get('/', 'index')->name('index');
        Route::post('/', 'store')->name('store');
        Route::put('/{booking}', 'update')->whereNumber('booking')->name('update');
        Route::delete('/{booking}', 'destroy')->whereNumber('booking')->name('destroy');
    });
