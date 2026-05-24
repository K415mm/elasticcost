<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class MsspSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::table('soc_roles')->upsert([
            [
                'id' => 1,
                'name' => 'Analyst Level 1 (L1)',
                'default_monthly_salary' => 4000.00,
                'description' => 'First-line alert triage, monitoring, and initial ticket investigation.',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => 2,
                'name' => 'Analyst Level 2 (L2)',
                'default_monthly_salary' => 6000.00,
                'description' => 'Incident analysis, containment orchestration, and deep security diagnostics.',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => 3,
                'name' => 'Analyst Level 3 (L3)',
                'default_monthly_salary' => 8500.00,
                'description' => 'Threat hunting, advanced forensics, malware reverse engineering, and threat intelligence mapping.',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => 4,
                'name' => 'SOC Engineer',
                'default_monthly_salary' => 7000.00,
                'description' => 'Maintains SIEM platform integrations, configures log parser rules, alerts, and system playbooks.',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => 5,
                'name' => 'SOC Manager',
                'default_monthly_salary' => 10000.00,
                'description' => 'Security operations leadership, SLA compliance auditing, reporting, and client governance.',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ], ['id']);
    }
}
