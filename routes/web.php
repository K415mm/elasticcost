<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ClientController;
use App\Http\Controllers\ClientAssetController;
use App\Http\Controllers\AssetTypeController;
use App\Http\Controllers\ScenarioController;
use App\Http\Controllers\SizingDashboardController;
use App\Http\Controllers\MsspCostingController;
use App\Http\Controllers\SystemSettingsController;

// Redirect home page to clients list
Route::get('/', function () {
    return redirect()->route('clients.index');
});

// Client CRUD
Route::resource('clients', ClientController::class)->only(['index', 'store', 'show', 'destroy']);

// Client Inventory Management
Route::post('clients/{client}/assets', [ClientAssetController::class, 'store'])->name('client-assets.store');
Route::put('clients/{client}/assets/{clientAsset}', [ClientAssetController::class, 'update'])->name('client-assets.update');
Route::delete('clients/{client}/assets/{clientAsset}', [ClientAssetController::class, 'destroy'])->name('client-assets.destroy');

// Benchmarks (Asset Types) Management
Route::get('settings/asset-types', [AssetTypeController::class, 'index'])->name('asset-types.index');
Route::post('settings/asset-types', [AssetTypeController::class, 'store'])->name('asset-types.store');
Route::put('settings/asset-types/{assetType}', [AssetTypeController::class, 'update'])->name('asset-types.update');

// Scenario Templates Management
Route::resource('settings/scenarios', ScenarioController::class)->except(['show']);

// System Configuration Menu
Route::get('settings/system', [SystemSettingsController::class, 'index'])->name('settings.system');
Route::post('settings/system', [SystemSettingsController::class, 'update'])->name('settings.system.update');
Route::post('settings/system/translations', [SystemSettingsController::class, 'updateTranslation'])->name('settings.system.translations.update');

// Sizing Dashboard & Reports
Route::get('clients/{client}/scenarios/{scenario}', [SizingDashboardController::class, 'show'])->name('sizing.show');
Route::get('clients/{client}/scenarios/{scenario}/export/excel', [SizingDashboardController::class, 'exportExcel'])->name('sizing.export.excel');
Route::get('clients/{client}/scenarios/{scenario}/export/markdown', [SizingDashboardController::class, 'exportMarkdown'])->name('sizing.export.markdown');
Route::get('clients/{client}/scenarios/{scenario}/export/word', [SizingDashboardController::class, 'exportWord'])->name('sizing.export.word');

// MSSP / SOC Costing Dashboard
Route::get('clients/{client}/scenarios/{scenario}/mssp-cost', [MsspCostingController::class, 'show'])->name('mssp.show');
Route::post('clients/{client}/scenarios/{scenario}/mssp-cost', [MsspCostingController::class, 'update'])->name('mssp.update');
Route::get('clients/{client}/scenarios/{scenario}/mssp-cost/export/excel', [MsspCostingController::class, 'exportExcel'])->name('mssp.export.excel');
Route::get('clients/{client}/scenarios/{scenario}/mssp-cost/export/word', [MsspCostingController::class, 'exportWord'])->name('mssp.export.word');
Route::get('clients/{client}/scenarios/{scenario}/mssp-cost/export/markdown', [MsspCostingController::class, 'exportMarkdown'])->name('mssp.export.markdown');
Route::post('clients/{client}/scenarios/{scenario}/mssp-cost/ask-ai', [MsspCostingController::class, 'askAi'])->name('mssp.ask-ai');

// Ollama connectivity test endpoint
Route::get('api/ollama-ping', [MsspCostingController::class, 'ollamaPing'])->name('ollama.ping');



