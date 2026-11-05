<?php

use App\Actions\CancelBooking;
use App\Models\Booking;

it('marks the booking as cancelled and keeps it for history', function () {
    $this->freezeSecond();
    $booking = Booking::factory()->create();

    app(CancelBooking::class)->handle($booking);

    expect($booking->fresh()->isActive())->toBeFalse()
        ->and($booking->fresh()->cancelled_at->equalTo(now()))->toBeTrue();
});

it('keeps the original time when cancelled twice', function () {
    $this->freezeSecond();
    $booking = Booking::factory()->create();

    app(CancelBooking::class)->handle($booking);
    $this->travel(5)->minutes();
    app(CancelBooking::class)->handle($booking);

    expect($booking->fresh()->cancelled_at->equalTo(now()->subMinutes(5)))->toBeTrue();
});
