<?php

namespace App\Services;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

class CustomerDetailsSchema
{
    public function ensure(): void
    {
        if ($this->isCurrent()) {
            return;
        }

        Cache::lock('ensure-customer-details-schema', 30)->block(10, function (): void {
            $this->addBirthday();
            $this->addSex();
            $this->addAddress();
        });
    }

    private function isCurrent(): bool
    {
        return Schema::hasColumn('users', 'birthday')
            && Schema::hasColumn('users', 'sex')
            && Schema::hasColumn('users', 'address');
    }

    private function addBirthday(): void
    {
        if (Schema::hasColumn('users', 'birthday')) {
            return;
        }

        Schema::table('users', function (Blueprint $table): void {
            $table->date('birthday')->nullable()->after('phone');
        });
    }

    private function addSex(): void
    {
        if (Schema::hasColumn('users', 'sex')) {
            return;
        }

        Schema::table('users', function (Blueprint $table): void {
            $table->string('sex', 30)->nullable()->after('birthday');
        });
    }

    private function addAddress(): void
    {
        if (Schema::hasColumn('users', 'address')) {
            return;
        }

        Schema::table('users', function (Blueprint $table): void {
            $table->text('address')->nullable()->after('sex');
        });
    }
}
