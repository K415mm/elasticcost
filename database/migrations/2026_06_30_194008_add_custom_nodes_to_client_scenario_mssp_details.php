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
            $table->json('custom_nodes')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('client_scenario_mssp_details', function (Blueprint $table) {
            $table->dropColumn('custom_nodes');
        });
    }
};
