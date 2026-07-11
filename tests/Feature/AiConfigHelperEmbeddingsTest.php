<?php

use App\Models\GlobalSetting;
use App\Services\AiConfigHelper;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    config(['ai.default_for_embeddings' => 'qwen']);
});

it('configures qwen embeddings by default', function () {
    $config = AiConfigHelper::configureEmbeddings();

    expect($config['provider'])->toBe('qwen')
        ->and($config['model'])->toBe('text-embedding-v3')
        ->and($config['dimensions'])->toBe(1024);

    expect(config('ai.providers.qwen.models.embeddings.default'))->toBe('text-embedding-v3');
    expect(config('ai.providers.qwen.models.embeddings.dimensions'))->toBe(1024);
    expect(config('ai.default_for_embeddings'))->toBe('qwen');
});

it('configures gemini free cloud embeddings when requested via global settings', function () {
    GlobalSetting::create([
        'key' => 'rag_embedding_provider',
        'value' => 'gemini',
    ]);

    GlobalSetting::create([
        'key' => 'gemini_api_key',
        'value' => 'test-gemini-key',
    ]);

    $config = AiConfigHelper::configureEmbeddings();

    expect($config['provider'])->toBe('gemini')
        ->and($config['model'])->toBe('text-embedding-004')
        ->and($config['dimensions'])->toBe(768);

    expect(config('ai.providers.gemini.models.embeddings.default'))->toBe('text-embedding-004');
    expect(config('ai.providers.gemini.models.embeddings.dimensions'))->toBe(768);
    expect(config('ai.providers.gemini.key'))->toBe('test-gemini-key');
    expect(config('ai.default_for_embeddings'))->toBe('gemini');
});
