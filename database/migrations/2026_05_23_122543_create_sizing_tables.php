<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('clients', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->timestamps();
        });

        Schema::create('asset_types', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->integer('avg_event_size_bytes');
            $table->string('calibration_mode'); // eps_per_device, monthly_gb_per_device, monthly_gb_total
            $table->decimal('min_eps_default', 12, 4);
            $table->decimal('avg_eps_default', 12, 4);
            $table->decimal('max_eps_default', 12, 4)->nullable();
            $table->decimal('max_monthly_gb_default', 12, 4)->nullable();
            $table->text('description')->nullable();
            $table->timestamps();
        });

        Schema::create('client_assets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained('clients')->onDelete('cascade');
            $table->foreignId('asset_type_id')->constrained('asset_types')->onDelete('cascade');
            $table->integer('device_count');
            $table->integer('custom_avg_event_size_bytes')->nullable();
            $table->decimal('custom_min_eps', 12, 4)->nullable();
            $table->decimal('custom_avg_eps', 12, 4)->nullable();
            $table->decimal('custom_max_eps', 12, 4)->nullable();
            $table->decimal('custom_max_monthly_gb', 12, 4)->nullable();
            $table->timestamps();
        });

        Schema::create('scenarios', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('description');
            $table->string('workload_profile'); // min, avg, max
            $table->integer('retention_days');
            $table->integer('hot_days');
            $table->integer('warm_days');
            $table->integer('cold_days');
            $table->integer('frozen_days');
            $table->integer('hot_replicas')->default(1);
            $table->integer('warm_replicas')->default(1);
            $table->integer('cold_replicas')->default(0);
            $table->integer('frozen_replicas')->default(0);
            $table->boolean('is_system_default')->default(false);
            $table->timestamps();
        });

        Schema::create('global_settings', function (Blueprint $table) {
            $table->string('key')->primary();
            $table->string('value');
            $table->string('description')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('global_settings');
        Schema::dropIfExists('scenarios');
        Schema::dropIfExists('client_assets');
        Schema::dropIfExists('asset_types');
        Schema::dropIfExists('clients');
    }
};
