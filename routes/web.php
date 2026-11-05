<?php

use App\Models\ClassType;
use App\Models\Slot;
use App\Models\Trainer;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/schedule')->name('home');

Route::livewire('schedule', 'pages::schedule')->name('schedule');

Route::middleware('auth')->group(function () {
    // Fortify and the starter kit's auth flows send people here after login.
    Route::redirect('dashboard', '/schedule')->name('dashboard');

    Route::livewire('my-bookings', 'pages::my-bookings')->name('my-bookings');
});

Route::middleware('auth')->prefix('admin')->name('admin.')->group(function () {
    Route::livewire('slots', 'pages::admin.slots')
        ->middleware('can:viewAny,'.Slot::class)
        ->name('slots');

    Route::livewire('slots/{slot}', 'pages::admin.slot-attendees')
        ->middleware('can:viewAttendees,slot')
        ->name('slots.attendees');

    Route::livewire('class-types', 'pages::admin.class-types')
        ->middleware('can:viewAny,'.ClassType::class)
        ->name('class-types');

    Route::livewire('trainers', 'pages::admin.trainers')
        ->middleware('can:viewAny,'.Trainer::class)
        ->name('trainers');
});

require __DIR__.'/settings.php';
