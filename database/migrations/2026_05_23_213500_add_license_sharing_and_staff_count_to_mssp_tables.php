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
        Schema::table('client_scenario_mssp_details', function (Blueprint $table) {
            $table->boolean('is_license_shared')->default(false)->after('local_ssd_monthly_cost_per_gb');
            $table->decimal('license_share_percentage', 5, 2)->default(100.00)->after('is_license_shared');
        });

        Schema::table('client_scenario_analyst_allocations', function (Blueprint $table) {
            $table->unsignedInteger('staff_count')->default(1)->after('custom_monthly_salary');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('client_scenario_mssp_details', function (Blueprint $table) {
            $table->dropColumn(['is_license_shared', 'license_share_percentage']);
        });

        Schema::table('client_scenario_analyst_allocations', function (Blueprint $table) {
            $table->dropColumn('staff_count');
        });
    }
};
