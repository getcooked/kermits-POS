<?php

namespace Tests;

use App\Models\DiningTable;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * Replace the venue's tables with ones seating these guest counts, numbered from 1.
     *
     * @param  list<int>  $seats
     */
    protected function useTables(array $seats): void
    {
        DiningTable::query()->delete();

        foreach (array_values($seats) as $index => $seatCount) {
            DiningTable::query()->create(['number' => $index + 1, 'seats' => $seatCount, 'active' => true]);
        }
    }
}
