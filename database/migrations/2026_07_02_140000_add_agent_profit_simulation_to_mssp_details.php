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
            $table->json('agent_profit_simulation_settings')->nullable()->after('custom_nodes');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('client_scenario_mssp_details', function (Blueprint $table) {
            $table->dropColumn('agent_profit_simulation_settings');
        });
    }
};
