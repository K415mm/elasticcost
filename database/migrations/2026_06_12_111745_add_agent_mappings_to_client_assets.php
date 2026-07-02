<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('client_assets', function (Blueprint $table) {
            $table->boolean('runs_siem_agent')->default(false)->after('custom_max_monthly_gb');
            $table->boolean('runs_mdr_agent')->default(false)->after('runs_siem_agent');
            $table->boolean('runs_edr_agent')->default(false)->after('runs_mdr_agent');
        });

        // Copy default mappings from asset_types to existing client_assets records
        $assetTypes = DB::table('asset_types')->get();
        foreach ($assetTypes as $type) {
            DB::table('client_assets')
                ->where('asset_type_id', $type->id)
                ->update([
                    'runs_siem_agent' => $type->runs_siem_agent,
                    'runs_mdr_agent' => $type->runs_mdr_agent,
                    'runs_edr_agent' => $type->runs_edr_agent,
                ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('client_assets', function (Blueprint $table) {
            $table->dropColumn([
                'runs_siem_agent',
                'runs_mdr_agent',
                'runs_edr_agent',
            ]);
        });
    }
};
