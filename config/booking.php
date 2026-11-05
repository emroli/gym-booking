<?php

return [

    // Members can cancel until this many hours before a class starts.
    'cancellation_cutoff_hours' => (int) env('BOOKING_CANCELLATION_CUTOFF_HOURS', 2),

    // How many days ahead a class can be booked.
    'window_days' => (int) env('BOOKING_WINDOW_DAYS', 14),

    // At or below this many spots, the calendar shows a class as "few left".
    'few_spots_threshold' => 3,

    // Longest "repeat weekly" series an admin can create in one go.
    'max_repeat_weeks' => 26,

];
