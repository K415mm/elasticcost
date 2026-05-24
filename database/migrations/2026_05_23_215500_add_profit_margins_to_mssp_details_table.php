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
            $table->decimal('assurance_benefit_percentage', 5, 2)->default(0.00)->after('local_ssd_monthly_cost_per_gb');
            $table->decimal('marketing_benefit_percentage', 5, 2)->default(0.00)->after('assurance_benefit_percentage');
            $table->decimal('soc_manager_benefit_percentage', 5, 2)->default(0.00)->after('marketing_benefit_percentage');
            $table->decimal('ceo_benefit_percentage', 5, 2)->default(0.00)->after('soc_manager_benefit_percentage');
            $table->decimal('fixed_profit_percentage', 5, 2)->default(0.00)->after('ceo_benefit_percentage');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('client_scenario_mssp_details', function (Blueprint $table) {
            $table->dropColumn([
                'assurance_benefit_percentage',
                'marketing_benefit_percentage',
                'soc_manager_benefit_percentage',
                'ceo_benefit_percentage',
                'fixed_profit_percentage',
            ]);
        });
    }
};
