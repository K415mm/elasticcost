<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessDocumentJob;
use App\Models\Document;
use App\Models\DocumentationChunk;
use App\Models\GlobalSetting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class FileManagerController extends Controller
{
    /**
     * Display the document file manager and RAG configuration interface.
     */
    public function index()
    {
        $documents = Document::orderBy('created_at', 'desc')->get();

        // Calculate storage stats
        $totalBytes = $documents->sum('size');
        $totalChunks = $documents->sum('chunk_count');

        // Let's assume a dummy free space or dynamic disk check
        $freeSpaceBytes = 127.7 * 1024 * 1024 * 1024; // 127.7 GB
        $totalDiskBytes = 256 * 1024 * 1024 * 1024;   // 256 GB

        if (function_exists('disk_free_space')) {
            try {
                $freeSpaceBytes = @disk_free_space(storage_path()) ?: $freeSpaceBytes;
                $totalDiskBytes = @disk_total_space(storage_path()) ?: $totalDiskBytes;
            } catch (\Throwable $e) {
            }
        }

        $freeSpaceGb = number_format($freeSpaceBytes / (1024 * 1024 * 1024), 1);
        $totalDiskGb = number_format($totalDiskBytes / (1024 * 1024 * 1024), 0);
        $usedPercentage = number_format((($totalDiskBytes - $freeSpaceBytes) / $totalDiskBytes) * 100, 0);

        // Active agents to configure
        $agents = [
            'ElasticCostAssistant' => 'ElasticCost Assistant',
            'RgSocEngineer' => 'RG SOC Engineer',
            'OfferAnalyst' => 'Offer Analyst',
            'SizingRegulator' => 'Sizing Regulator',
        ];

        $agentConfigs = [];
        foreach ($agents as $key => $name) {
            $agentConfigs[$key] = [
                'name' => $name,
                'enabled' => (bool) GlobalSetting::getValue("ai_rag_enabled_{$key}", true),
                'threshold' => (float) GlobalSetting::getValue("ai_rag_threshold_{$key}", 0.30),
                'max_chunks' => (int) GlobalSetting::getValue("ai_rag_max_chunks_{$key}", 3),
            ];
        }

        $embeddingProvider = GlobalSetting::getValue('rag_embedding_provider', 'ollama');
        $embeddingModel = GlobalSetting::getValue('rag_embedding_model', 'nomic-embed-text');

        // Provider connection settings for the scan-models functionality
        $providerSettings = [
            'ollama_url' => GlobalSetting::getValue('ollama_url', 'http://localhost:11434'),
            'lmstudio_url' => GlobalSetting::getValue('lmstudio_url', 'http://localhost:1234/v1'),
            'gemini_api_key' => GlobalSetting::getValue('gemini_api_key', ''),
            'openrouter_api_key' => GlobalSetting::getValue('openrouter_api_key', ''),
            'qwen_url' => GlobalSetting::getValue('qwen_url', 'https://dashscope-intl.aliyuncs.com/compatible-mode/v1'),
            'qwen_api_key' => GlobalSetting::getValue('qwen_api_key', ''),
        ];

        return view('settings.file_manager', compact(
            'documents', 'totalBytes', 'totalChunks',
            'freeSpaceGb', 'totalDiskGb', 'usedPercentage',
            'agentConfigs', 'embeddingProvider', 'embeddingModel',
            'providerSettings'
        ));
    }

    /**
     * Store a newly uploaded document.
     */
    public function store(Request $request)
    {
        $request->validate([
            'document' => 'required|file|max:10240|mimes:txt,md,docx,csv,json,html',
        ]);

        $file = $request->file('document');
        $originalName = $file->getClientOriginalName();

        // Store on local disk under documents folder
        $filename = time().'_'.uniqid().'.'.$file->getClientOriginalExtension();
        $file->storeAs('documents', $filename);

        $document = Document::create([
            'original_name' => $originalName,
            'filename' => $filename,
            'mime_type' => $file->getMimeType() ?: $file->getClientMimeType(),
            'size' => $file->getSize(),
            'status' => 'pending',
            'chunk_count' => 0,
        ]);

        // Dispatch background job
        ProcessDocumentJob::dispatch($document->id);

        return redirect()->route('settings.files')
            ->with('success', 'Document uploaded successfully. Processing vectors in the background.');
    }

    /**
     * Remove the document and its chunks from the system.
     */
    public function destroy(Document $document)
    {
        // Delete the physical file
        Storage::delete('documents/'.$document->filename);

        // Delete from database (associated chunks are cascade deleted via foreign key constraint)
        $document->delete();

        return redirect()->route('settings.files')
            ->with('success', 'Document and its vector embeddings removed successfully.');
    }

    /**
     * Update Agent RAG Configurations.
     */
    public function updateAgentConfig(Request $request)
    {
        $validated = $request->validate([
            'agent_key' => 'required|string',
            'enabled' => 'nullable|boolean',
            'threshold' => 'required|numeric|between:0,1',
            'max_chunks' => 'required|integer|between:1,10',
        ]);

        $key = $validated['agent_key'];
        $enabled = $request->has('enabled');

        GlobalSetting::updateOrCreate(
            ['key' => "ai_rag_enabled_{$key}"],
            ['value' => $enabled ? '1' : '0', 'description' => "Whether RAG is enabled for {$key}"]
        );

        GlobalSetting::updateOrCreate(
            ['key' => "ai_rag_threshold_{$key}"],
            ['value' => $validated['threshold'], 'description' => "RAG semantic similarity threshold for {$key}"]
        );

        GlobalSetting::updateOrCreate(
            ['key' => "ai_rag_max_chunks_{$key}"],
            ['value' => $validated['max_chunks'], 'description' => "Max RAG chunks to inject for {$key}"]
        );

        return response()->json([
            'success' => true,
            'message' => 'Agent RAG configurations updated successfully.',
        ]);
    }

    /**
     * Update decoupling RAG embedding settings.
     */
    public function updateEmbeddingSettings(Request $request)
    {
        $validated = $request->validate([
            'rag_embedding_provider' => 'required|string|in:ollama,lmstudio,gemini,openrouter,qwen',
            'rag_embedding_model' => 'required|string',
        ]);

        GlobalSetting::updateOrCreate(
            ['key' => 'rag_embedding_provider'],
            ['value' => $validated['rag_embedding_provider'], 'description' => 'Decoupled RAG embedding provider']
        );

        GlobalSetting::updateOrCreate(
            ['key' => 'rag_embedding_model'],
            ['value' => $validated['rag_embedding_model'], 'description' => 'Decoupled RAG embedding model']
        );

        return response()->json([
            'success' => true,
            'message' => 'RAG embedding configurations updated successfully.',
        ]);
    }

    /**
     * Return document chunks as JSON for semantic inspection/monitoring.
     */
    public function showChunks(Document $document)
    {
        $chunks = DocumentationChunk::where('document_id', $document->id)
            ->orderBy('id', 'asc')
            ->get(['id', 'chunk_text', 'created_at']);

        return response()->json([
            'document' => [
                'id' => $document->id,
                'name' => $document->original_name,
            ],
            'chunks' => $chunks,
        ]);
    }
}
