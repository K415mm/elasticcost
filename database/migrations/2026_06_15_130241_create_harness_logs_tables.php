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
        Schema::create('harness_sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->text('prompt');
            $table->text('response')->nullable();
            $table->string('method');
            $table->integer('iterations')->default(0);
            $table->integer('total_duration_ms')->default(0);
            $table->timestamps();
        });

        Schema::create('harness_details', function (Blueprint $table) {
            $table->id();
            $table->string('session_id')->index();
            $table->string('type'); // 'llm_call' or 'tool_call'
            $table->string('name'); // model name or tool name
            $table->longText('payload');
            $table->longText('response');
            $table->integer('duration_ms')->default(0);
            $table->integer('tokens_prompt')->default(0);
            $table->integer('tokens_completion')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('harness_details');
        Schema::dropIfExists('harness_sessions');
    }
};
