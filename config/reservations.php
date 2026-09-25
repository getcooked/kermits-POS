<?php

return [
    'opening_time' => '08:00:00',
    'last_start_time' => '22:00:00',
    'closing_time' => '23:00:00',
    'duration_minutes' => 120,
    'hold_minutes' => 30,
    // Cleanup time a table needs between bookings; editable in Table Management.
    'turnover_minutes' => 15,
    // Initial tables; after migrating, tables are managed in Table Management.
    'table_capacities' => [2, 2, 2, 4, 4, 4, 4, 12],
    'table_fees' => [1 => 100, 2 => 150, 4 => 250, 8 => 450, 12 => 650],
    'exclusive_fee' => 5000,
];
