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
        Schema::create('soc_roles', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->decimal('default_monthly_salary', 12, 2);
            $table->text('description')->nullable();
            $table->timestamps();
        });

        Schema::create('client_scenario_mssp_details', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained('clients')->onDelete('cascade');
            $table->foreignId('scenario_id')->constrained('scenarios')->onDelete('cascade');
            $table->decimal('one_time_setup_cost', 12, 2)->default(0.00);
            $table->decimal('monthly_maintenance_cost', 12, 2)->default(0.00);
            $table->decimal('ram_monthly_cost_per_gb', 12, 4)->default(1.5000);
            $table->decimal('nvme_ssd_monthly_cost_per_gb', 12, 4)->default(0.1500);
            $table->decimal('sata_ssd_monthly_cost_per_gb', 12, 4)->default(0.0800);
            $table->decimal('local_ssd_monthly_cost_per_gb', 12, 4)->default(0.1200);
            $table->timestamps();

            $table->unique(['client_id', 'scenario_id'], 'client_scenario_mssp_unique');
        });

        Schema::create('client_scenario_analyst_allocations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mssp_details_id')
                ->constrained('client_scenario_mssp_details')
                ->onDelete('cascade')
                ->name('fk_csaa_mssp_details');
            $table->foreignId('soc_role_id')->constrained('soc_roles')->onDelete('cascade');
            $table->decimal('allocation_percentage', 5, 2)->default(0.00); // 0.00 to 100.00
            $table->decimal('custom_monthly_salary', 12, 2)->nullable();
            $table->timestamps();

            $table->unique(['mssp_details_id', 'soc_role_id'], 'mssp_details_role_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('client_scenario_analyst_allocations');
        Schema::dropIfExists('client_scenario_mssp_details');
        Schema::dropIfExists('soc_roles');
    }
};
