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
        Schema::table('asset_types', function (Blueprint $table) {
            $table->boolean('runs_siem_agent')->default(false)->after('calibration_mode');
            $table->boolean('runs_mdr_agent')->default(false)->after('runs_siem_agent');
            $table->boolean('runs_edr_agent')->default(false)->after('runs_mdr_agent');
        });

        Schema::table('client_scenario_mssp_details', function (Blueprint $table) {
            $table->decimal('elastic_cloud_monthly_cost_per_gb_ram', 12, 4)->default(45.0000)->after('local_ssd_monthly_cost_per_gb');
            $table->decimal('siem_agent_monthly_cost_per_device', 12, 4)->default(15.0000)->after('elastic_cloud_monthly_cost_per_gb_ram');
            $table->decimal('mdr_agent_monthly_cost_per_device', 12, 4)->default(30.0000)->after('siem_agent_monthly_cost_per_device');
            $table->decimal('edr_agent_monthly_cost_per_device', 12, 4)->default(10.0000)->after('mdr_agent_monthly_cost_per_device');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('client_scenario_mssp_details', function (Blueprint $table) {
            $table->dropColumn([
                'elastic_cloud_monthly_cost_per_gb_ram',
                'siem_agent_monthly_cost_per_device',
                'mdr_agent_monthly_cost_per_device',
                'edr_agent_monthly_cost_per_device',
            ]);
        });

        Schema::table('asset_types', function (Blueprint $table) {
            $table->dropColumn([
                'runs_siem_agent',
                'runs_mdr_agent',
                'runs_edr_agent',
            ]);
        });
    }
};
