<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\RolePermission;
use Illuminate\Database\Seeder;

class PermissionSeeder extends Seeder
{
    /**
     * Seed all application permissions and default role permissions.
     */
    public function run(): void
    {
        $modules = [
            ['key' => 'dashboard', 'label' => 'Dashboard', 'description' => 'Main landing page with KPIs', 'category' => 'Core', 'icon' => 'bi-cpu', 'sort_order' => 1],
            ['key' => 'clients', 'label' => 'Clients', 'description' => 'Client CRUD and details', 'category' => 'Core', 'icon' => 'bi-people', 'sort_order' => 2],
            ['key' => 'sizing', 'label' => 'Sizing Dashboard', 'description' => 'Per-client/scenario sizing, exports, AI analysis', 'category' => 'Core', 'icon' => 'bi-speedometer2', 'sort_order' => 3],
            ['key' => 'mssp_costing', 'label' => 'MSSP Costing', 'description' => 'SOC costing proposals and exports', 'category' => 'Core', 'icon' => 'bi-cash-stack', 'sort_order' => 4],
            ['key' => 'profit_simulator', 'label' => 'Profit Simulator', 'description' => 'Revenue and profit simulation dashboard', 'category' => 'Core', 'icon' => 'bi-graph-up-arrow', 'sort_order' => 5],
            ['key' => 'ai_chat', 'label' => 'AI Chat', 'description' => 'AI assistant chat interface', 'category' => 'AI', 'icon' => 'bi-chat-dots', 'sort_order' => 6],
            ['key' => 'asset_types', 'label' => 'Asset Types (Benchmarks)', 'description' => 'Ingest benchmark management', 'category' => 'Settings', 'icon' => 'bi-hdd-stack', 'sort_order' => 7],
            ['key' => 'scenarios', 'label' => 'Scenario Templates', 'description' => 'Scenario template CRUD', 'category' => 'Settings', 'icon' => 'bi-sliders', 'sort_order' => 8],
            ['key' => 'system_settings', 'label' => 'System Settings', 'description' => 'Global configuration, translations, AI config', 'category' => 'Settings', 'icon' => 'bi-gear', 'sort_order' => 9],
            ['key' => 'file_manager', 'label' => 'File Manager (RAG)', 'description' => 'Document management and RAG configuration', 'category' => 'Settings', 'icon' => 'bi-folder', 'sort_order' => 10],
            ['key' => 'ai_agents', 'label' => 'AI Agents', 'description' => 'Agent registry and orchestration', 'category' => 'AI', 'icon' => 'bi-robot', 'sort_order' => 11],
            ['key' => 'harness_analytics', 'label' => 'Harness Analytics', 'description' => 'Diagnostics dashboard', 'category' => 'Diagnostics', 'icon' => 'bi-activity', 'sort_order' => 12],
            ['key' => 'test_compare', 'label' => 'Test Compare', 'description' => 'Test suite and traces', 'category' => 'Diagnostics', 'icon' => 'bi-bug', 'sort_order' => 13],
            ['key' => 'user_management', 'label' => 'User Management', 'description' => 'Manage users, roles, and permissions', 'category' => 'Administration', 'icon' => 'bi-person-gear', 'sort_order' => 14],
            ['key' => 'token_management', 'label' => 'Token Management', 'description' => 'Passport OAuth token management', 'category' => 'Administration', 'icon' => 'bi-key', 'sort_order' => 15],
        ];

        $roles = ['client', 'manager', 'sales_manager', 'partner', 'ceo'];

        $defaultMatrix = [
            'dashboard' => ['client', 'manager', 'sales_manager', 'partner', 'ceo'],
            'clients' => ['client', 'manager', 'sales_manager', 'ceo'],
            'sizing' => ['client', 'manager', 'sales_manager', 'ceo'],
            'mssp_costing' => ['manager', 'sales_manager', 'ceo'],
            'profit_simulator' => ['manager', 'sales_manager', 'ceo'],
            'ai_chat' => ['client', 'manager', 'sales_manager', 'partner', 'ceo'],
            'asset_types' => ['manager', 'ceo'],
            'scenarios' => ['manager', 'ceo'],
            'system_settings' => ['ceo'],
            'file_manager' => ['manager', 'ceo'],
            'ai_agents' => ['manager', 'ceo'],
            'harness_analytics' => ['manager', 'ceo'],
            'test_compare' => ['manager', 'ceo'],
            'user_management' => ['manager', 'ceo'],
            'token_management' => ['manager', 'ceo'],
        ];

        foreach ($modules as $module) {
            $permission = Permission::firstOrCreate(
                ['key' => $module['key']],
                [
                    'label' => $module['label'],
                    'description' => $module['description'],
                    'category' => $module['category'],
                    'icon' => $module['icon'],
                    'sort_order' => $module['sort_order'],
                ]
            );

            $allowedRoles = $defaultMatrix[$module['key']] ?? [];

            foreach ($roles as $role) {
                RolePermission::firstOrCreate(
                    [
                        'role' => $role,
                        'permission_id' => $permission->id,
                    ],
                    [
                        'is_allowed' => in_array($role, $allowedRoles),
                    ]
                );
            }
        }
    }
}
