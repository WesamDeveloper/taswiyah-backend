<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenants', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('custom_domain')->nullable()->unique();
            $table->string('brand_color')->default('#4F46E5');
            $table->string('logo_url')->nullable();
            $table->string('app_name')->default('DebtFlow AI');
            $table->integer('health_score')->default(100);
            $table->string('status')->default('active');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenants');
    }
};
