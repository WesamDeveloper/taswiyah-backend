<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            if (!Schema::hasColumn('tenants', 'auto_remind_day')) {
                $table->integer('auto_remind_day')->nullable()->after('status');
            }
        });

        Schema::table('customers', function (Blueprint $table) {
            if (!Schema::hasColumn('customers', 'reminder_frequency_days')) {
                $table->integer('reminder_frequency_days')->nullable()->after('primary_phone');
            }
            if (!Schema::hasColumn('customers', 'next_reminder_date')) {
                $table->date('next_reminder_date')->nullable()->after('reminder_frequency_days');
            }
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn('auto_remind_day');
        });

        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn(['reminder_frequency_days', 'next_reminder_date']);
        });
    }
};
