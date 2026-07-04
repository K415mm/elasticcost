<?php

use App\Http\Controllers\AgentJobStatusController;
use App\Http\Controllers\AgentProfitSimulatorController;
use App\Http\Controllers\AiAgentController;
use App\Http\Controllers\AiChatController;
use App\Http\Controllers\AssetTypeController;
use App\Http\Controllers\ClientAssetController;
use App\Http\Controllers\ClientController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\FileManagerController;
use App\Http\Controllers\MsspCostingController;
use App\Http\Controllers\RolePermissionController;
use App\Http\Controllers\ScenarioController;
use App\Http\Controllers\SizingDashboardController;
use App\Http\Controllers\SystemSettingsController;
use App\Http\Controllers\TestCompareController;
use App\Http\Controllers\TokenManagementController;
use App\Http\Controllers\UserManagementController;
use App\Http\Controllers\WebAuthController;
use Illuminate\Support\Facades\Route;

// Auth routes (public)
Route::get('login', [WebAuthController::class, 'showLogin'])->name('login');
Route::post('login', [WebAuthController::class, 'login'])->name('login.post');
Route::get('register', [WebAuthController::class, 'showRegister'])->name('register');
Route::post('register', [WebAuthController::class, 'register'])->name('register.post');
Route::post('logout', [WebAuthController::class, 'logout'])->name('logout');

// Redirect home page to dashboard
Route::get('/', function () {
    return redirect()->route('dashboard');
});

// All routes below require authentication
Route::middleware('auth')->group(function () {

    // Dedicated Profit & Revenue Simulator Dashboard
    Route::middleware('permission:profit_simulator')->group(function () {
        Route::get('simulator', [AgentProfitSimulatorController::class, 'index'])->name('simulator.index');
        Route::post('simulator/{client}/{scenario}', [AgentProfitSimulatorController::class, 'update'])->name('simulator.update');
        Route::post('simulator/{client}/{scenario}/ai-analysis', [AgentProfitSimulatorController::class, 'runAiAnalysis'])->name('simulator.ai-analysis');
    });

    // Main Sizing Dashboard landing page
    Route::get('dashboard', [DashboardController::class, 'index'])->name('dashboard')->middleware('permission:dashboard');

    // Client CRUD
    Route::middleware('permission:clients')->group(function () {
        Route::resource('clients', ClientController::class)->only(['index', 'store', 'show', 'destroy']);

        // Client Inventory Management
        Route::post('clients/{client}/assets', [ClientAssetController::class, 'store'])->name('client-assets.store');
        Route::put('clients/{client}/assets-bulk', [ClientAssetController::class, 'updateBulk'])->name('client-assets.update-bulk');
        Route::put('clients/{client}/assets/{clientAsset}', [ClientAssetController::class, 'update'])->name('client-assets.update');
        Route::delete('clients/{client}/assets/{clientAsset}', [ClientAssetController::class, 'destroy'])->name('client-assets.destroy');
    });

    // Benchmarks (Asset Types) Management
    Route::middleware('permission:asset_types')->group(function () {
        Route::get('settings/asset-types', [AssetTypeController::class, 'index'])->name('asset-types.index');
        Route::post('settings/asset-types', [AssetTypeController::class, 'store'])->name('asset-types.store');
        Route::put('settings/asset-types/{assetType}', [AssetTypeController::class, 'update'])->name('asset-types.update');
    });

    // Scenario Templates Management
    Route::middleware('permission:scenarios')->group(function () {
        Route::resource('settings/scenarios', ScenarioController::class)->except(['show']);
    });

    // System Configuration Menu
    Route::middleware('permission:system_settings')->group(function () {
        Route::get('settings/system', [SystemSettingsController::class, 'index'])->name('settings.system');
        Route::post('settings/system', [SystemSettingsController::class, 'update'])->name('settings.system.update');
        Route::post('settings/system/translations', [SystemSettingsController::class, 'updateTranslation'])->name('settings.system.translations.update');
        Route::post('settings/system/ai', [SystemSettingsController::class, 'updateAi'])->name('settings.system.ai.update');
    });

    // File Manager & RAG System
    Route::middleware('permission:file_manager')->group(function () {
        Route::get('settings/files', [FileManagerController::class, 'index'])->name('settings.files');
        Route::post('settings/files', [FileManagerController::class, 'store'])->name('settings.files.store');
        Route::delete('settings/files/{document}', [FileManagerController::class, 'destroy'])->name('settings.files.destroy');
        Route::post('settings/files/agent-config', [FileManagerController::class, 'updateAgentConfig'])->name('settings.files.agent-config');
        Route::post('settings/files/embedding-config', [FileManagerController::class, 'updateEmbeddingSettings'])->name('settings.files.embedding-config');
        Route::get('settings/files/{document}/chunks', [FileManagerController::class, 'showChunks'])->name('settings.files.chunks');
    });

    // Sizing Dashboard & Reports
    Route::middleware('permission:sizing')->group(function () {
        Route::get('clients/{client}/scenarios/{scenario}', [SizingDashboardController::class, 'show'])->name('sizing.show');
        Route::get('clients/{client}/scenarios/{scenario}/export/excel', [SizingDashboardController::class, 'exportExcel'])->name('sizing.export.excel');
        Route::get('clients/{client}/scenarios/{scenario}/export/markdown', [SizingDashboardController::class, 'exportMarkdown'])->name('sizing.export.markdown');
        Route::get('clients/{client}/scenarios/{scenario}/export/word', [SizingDashboardController::class, 'exportWord'])->name('sizing.export.word');
        Route::post('clients/{client}/scenarios/{scenario}/sizing/analyze-ai', [SizingDashboardController::class, 'analyzeSizingAi'])->name('sizing.analyze-ai');
        Route::post('clients/{client}/scenarios/{scenario}/custom-nodes', [SizingDashboardController::class, 'saveCustomNodes'])->name('sizing.custom-nodes.save');
        Route::post('clients/{client}/scenarios/{scenario}/custom-nodes/reset', [SizingDashboardController::class, 'resetCustomNodes'])->name('sizing.custom-nodes.reset');
    });

    // MSSP / SOC Costing Dashboard
    Route::middleware('permission:mssp_costing')->group(function () {
        Route::get('clients/{client}/scenarios/{scenario}/mssp-cost', [MsspCostingController::class, 'show'])->name('mssp.show');
        Route::post('clients/{client}/scenarios/{scenario}/mssp-cost', [MsspCostingController::class, 'update'])->name('mssp.update');
        Route::get('clients/{client}/scenarios/{scenario}/mssp-cost/export/excel', [MsspCostingController::class, 'exportExcel'])->name('mssp.export.excel');
        Route::get('clients/{client}/scenarios/{scenario}/mssp-cost/export/word', [MsspCostingController::class, 'exportWord'])->name('mssp.export.word');
        Route::get('clients/{client}/scenarios/{scenario}/mssp-cost/export/markdown', [MsspCostingController::class, 'exportMarkdown'])->name('mssp.export.markdown');
        Route::post('clients/{client}/scenarios/{scenario}/mssp-cost/ask-ai', [MsspCostingController::class, 'askAi'])->name('mssp.ask-ai');
        Route::post('clients/{client}/scenarios/{scenario}/mssp-cost/reset-simulation', [MsspCostingController::class, 'resetSimulation'])->name('mssp.reset-simulation');
    });

    // Ollama connectivity test endpoint
    Route::get('api/ollama-ping', [MsspCostingController::class, 'ollamaPing'])->name('ollama.ping');

    // AI Chat Analyst Router
    Route::middleware('permission:ai_chat')->group(function () {
        Route::get('ai-chat/{id?}', [AiChatController::class, 'index'])->name('ai-chat.index');
        Route::post('ai-chat/message/{id?}', [AiChatController::class, 'storeMessage'])->name('ai-chat.message');
        Route::delete('ai-chat/{id}', [AiChatController::class, 'destroy'])->name('ai-chat.destroy');
    });

    // Agent job status polling (for SweetAlert2 notification banner)
    Route::get('api/agent-job-status/{jobId}', [AgentJobStatusController::class, 'show'])->name('agent.job.status');

    // phpkaiharness Test Compare Suite
    Route::middleware('permission:test_compare')->group(function () {
        Route::get('test-compare', [TestCompareController::class, 'index'])->name('test-compare.index');
        Route::post('test-compare/run', [TestCompareController::class, 'run'])->name('test-compare.run');
        Route::get('test-compare/dataset', [TestCompareController::class, 'dataset'])->name('test-compare.dataset');
        Route::get('test-compare/trace/{mode}/{index}', [TestCompareController::class, 'trace'])->name('test-compare.trace');
    });

    // AI Agents Registry & Orchestration
    Route::middleware('permission:ai_agents')->group(function () {
        Route::get('settings/agents', [AiAgentController::class, 'index'])->name('settings.agents');
        Route::post('settings/agents/config', [AiAgentController::class, 'updateConfig'])->name('settings.agents.config');
        Route::post('settings/agents/analyze', [AiAgentController::class, 'runAnalysis'])->name('settings.agents.analyze');
        Route::post('settings/agents/orchestrate', [AiAgentController::class, 'runOrchestratedAction'])->name('settings.agents.orchestrate');
    });

    // User Management (RBAC)
    Route::middleware('permission:user_management')->group(function () {
        Route::get('users', [UserManagementController::class, 'index'])->name('users.index');
        Route::post('users', [UserManagementController::class, 'store'])->middleware(['precognitive'])->name('users.store');
        Route::put('users/{user}', [UserManagementController::class, 'update'])->middleware(['precognitive'])->name('users.update');
        Route::delete('users/{user}', [UserManagementController::class, 'destroy'])->name('users.destroy');
    });

    // Role Permission Matrix
    Route::middleware('permission:user_management')->group(function () {
        Route::get('roles/permissions', [RolePermissionController::class, 'index'])->name('roles.permissions');
        Route::put('roles/permissions', [RolePermissionController::class, 'update'])->name('roles.permissions.update');
    });

    // Token Management (Passport)
    Route::middleware('permission:token_management')->group(function () {
        Route::get('tokens', [TokenManagementController::class, 'index'])->name('tokens.index');
        Route::delete('tokens/{tokenId}', [TokenManagementController::class, 'destroy'])->name('tokens.destroy');
        Route::delete('tokens/user/{user}/all', [TokenManagementController::class, 'revokeAllForUser'])->name('tokens.revoke-all');
    });

}); // End auth middleware group
