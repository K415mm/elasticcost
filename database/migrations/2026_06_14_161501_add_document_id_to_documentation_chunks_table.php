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
        Schema::table('documentation_chunks', function (Blueprint $table) {
            $table->foreignId('document_id')
                ->nullable()
                ->constrained('documents')
                ->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('documentation_chunks', function (Blueprint $table) {
            // Drop foreign key is skipped on sqlite to prevent errors in unit tests
            if (DB::connection()->getDriverName() !== 'sqlite') {
                $table->dropForeign(['document_id']);
            }
            $table->dropColumn('document_id');
        });
    }
};
