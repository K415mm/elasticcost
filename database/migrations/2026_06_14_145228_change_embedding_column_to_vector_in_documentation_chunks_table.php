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
        $isSqlite = DB::connection()->getDriverName() === 'sqlite';

        if ($isSqlite) {
            return;
        }

        // Ensure extension is active
        DB::statement('CREATE EXTENSION IF NOT EXISTS vector;');

        Schema::table('documentation_chunks', function (Blueprint $table) {
            $table->dropColumn('embedding');
        });

        // Add embedding column as vector type (unbounded dimensions for provider flexibility)
        DB::statement('ALTER TABLE documentation_chunks ADD COLUMN embedding vector;');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $isSqlite = DB::connection()->getDriverName() === 'sqlite';

        if ($isSqlite) {
            return;
        }

        Schema::table('documentation_chunks', function (Blueprint $table) {
            $table->dropColumn('embedding');
        });

        Schema::table('documentation_chunks', function (Blueprint $table) {
            $table->longText('embedding');
        });
    }
};
