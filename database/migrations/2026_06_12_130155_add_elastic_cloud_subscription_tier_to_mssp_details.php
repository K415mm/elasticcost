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
            $table->string('elastic_cloud_subscription_tier')->default('platinum')->after('elastic_cloud_monthly_cost_per_gb_ram');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('client_scenario_mssp_details', function (Blueprint $table) {
            $table->dropColumn('elastic_cloud_subscription_tier');
        });
    }
};
