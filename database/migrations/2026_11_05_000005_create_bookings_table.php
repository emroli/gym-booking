<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bookings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('slot_id')->constrained()->cascadeOnDelete();
            $table->dateTime('cancelled_at')->nullable();
            $table->timestamps();

            // A member has at most one row per slot. Rebooking after a
            // cancellation reactivates this row instead of adding another.
            $table->unique(['user_id', 'slot_id']);
            $table->index(['slot_id', 'cancelled_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bookings');
    }
};
