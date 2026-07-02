<?php

namespace Tests\Feature;

use App\Jobs\ProcessDocumentJob;
use App\Models\Document;
use App\Models\DocumentationChunk;
use App\Models\GlobalSetting;
use App\Services\DocumentParser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class FileManagerTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test the file manager page loads correctly and displays initial structure.
     */
    public function test_file_manager_dashboard_renders_successfully(): void
    {
        $response = $this->get(route('settings.files'));

        $response->assertStatus(200);
        $response->assertSee('File Manager');
        $response->assertSee('Semantic RAG');
        $response->assertSee('AGENT RAG CONFIGURATION');
        $response->assertSee('ElasticCost Assistant');
        $response->assertSee('RG SOC Engineer');
    }

    /**
     * Test uploading a document validates file extension and dispatches background worker job.
     */
    public function test_document_upload_success_and_queue_dispatch(): void
    {
        Storage::fake('local');
        Bus::fake();

        $file = UploadedFile::fake()->create('architecture_guide.md', 500, 'text/markdown');

        $response = $this->post(route('settings.files.store'), [
            'document' => $file,
        ]);

        $response->assertRedirect(route('settings.files'));
        $response->assertSessionHas('success');

        // Assert DB entry was created
        $this->assertDatabaseHas('documents', [
            'original_name' => 'architecture_guide.md',
            'status' => 'pending',
            'chunk_count' => 0,
        ]);

        $document = Document::where('original_name', 'architecture_guide.md')->first();
        $this->assertNotNull($document);

        // Assert file was physically stored
        Storage::assertExists('documents/'.$document->filename);

        // Assert processing job was dispatched
        Bus::assertDispatched(ProcessDocumentJob::class, function ($job) use ($document) {
            return $job->documentId === $document->id;
        });
    }

    /**
     * Test file uploads reject unsupported extensions.
     */
    public function test_document_upload_validation_fails_for_invalid_mime(): void
    {
        Storage::fake('local');
        Bus::fake();

        // Unsupported extension like png
        $file = UploadedFile::fake()->create('malicious.exe', 100, 'application/octet-stream');

        $response = $this->post(route('settings.files.store'), [
            'document' => $file,
        ]);

        $response->assertSessionHasErrors(['document']);
        $this->assertDatabaseEmpty('documents');
        Bus::assertNotDispatched(ProcessDocumentJob::class);
    }

    /**
     * Test displaying JSON vector chunks for a completed document.
     */
    public function test_show_chunks_returns_json_structure(): void
    {
        $document = Document::create([
            'original_name' => 'guide.txt',
            'filename' => '1234_guide.txt',
            'mime_type' => 'text/plain',
            'size' => 1000,
            'status' => 'completed',
            'chunk_count' => 2,
        ]);

        $chunk1 = DocumentationChunk::create([
            'document_id' => $document->id,
            'source_file' => 'guide.txt',
            'chunk_text' => 'Segment 1 text content here.',
            'embedding' => array_fill(0, 384, 0.1),
        ]);

        $chunk2 = DocumentationChunk::create([
            'document_id' => $document->id,
            'source_file' => 'guide.txt',
            'chunk_text' => 'Segment 2 text content here.',
            'embedding' => array_fill(0, 384, 0.2),
        ]);

        $response = $this->get(route('settings.files.chunks', $document->id));

        $response->assertStatus(200);
        $response->assertJson([
            'document' => [
                'id' => $document->id,
                'name' => 'guide.txt',
            ],
            'chunks' => [
                [
                    'id' => $chunk1->id,
                    'chunk_text' => 'Segment 1 text content here.',
                ],
                [
                    'id' => $chunk2->id,
                    'chunk_text' => 'Segment 2 text content here.',
                ],
            ],
        ]);
    }

    /**
     * Test updating agent RAG configs modifies global settings.
     */
    public function test_update_agent_config_saves_to_global_settings(): void
    {
        $postData = [
            'agent_key' => 'RgSocEngineer',
            'enabled' => '1',
            'threshold' => 0.45,
            'max_chunks' => 5,
        ];

        $response = $this->post(route('settings.files.agent-config'), $postData);

        $response->assertStatus(200);
        $response->assertJson(['success' => true]);

        // Assert settings were updated in the DB
        $this->assertEquals('1', GlobalSetting::getValue('ai_rag_enabled_RgSocEngineer'));
        $this->assertEquals('0.45', GlobalSetting::getValue('ai_rag_threshold_RgSocEngineer'));
        $this->assertEquals('5', GlobalSetting::getValue('ai_rag_max_chunks_RgSocEngineer'));
    }

    /**
     * Test deleting a document deletes the storage file and cascades chunks delete.
     */
    public function test_delete_document_removes_physical_file_and_cascades_chunks(): void
    {
        Storage::fake('local');

        $document = Document::create([
            'original_name' => 'remove_me.md',
            'filename' => '9999_remove_me.md',
            'mime_type' => 'text/markdown',
            'size' => 150,
            'status' => 'completed',
            'chunk_count' => 1,
        ]);

        // Put fake file on storage so it can be deleted
        Storage::put('documents/'.$document->filename, 'Fake file contents');

        $chunk = DocumentationChunk::create([
            'document_id' => $document->id,
            'source_file' => 'remove_me.md',
            'chunk_text' => 'Deleted segment content.',
            'embedding' => array_fill(0, 384, 0.0),
        ]);

        $response = $this->delete(route('settings.files.destroy', $document->id));

        $response->assertRedirect(route('settings.files'));
        $response->assertSessionHas('success');

        // Verify database records are deleted
        $this->assertDatabaseMissing('documents', ['id' => $document->id]);
        $this->assertDatabaseMissing('documentation_chunks', ['id' => $chunk->id]);

        // Verify storage file is removed
        Storage::assertMissing('documents/'.$document->filename);
    }

    /**
     * Test the DocumentParser can successfully extract text from a Word (.docx) file
     * using the direct XML parser fallback.
     */
    public function test_document_parser_docx_direct_parsing_works(): void
    {
        $tempDocx = tempnam(sys_get_temp_dir(), 'docx_test').'.docx';

        $zip = new \ZipArchive;
        if ($zip->open($tempDocx, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) === true) {
            $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
            <w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main" xmlns:m="http://schemas.openxmlformats.org/officeDocument/2006/math">
                <w:body>
                    <w:p>
                        <w:r>
                            <w:t>Hello world, this is a test docx file.</w:t>
                        </w:r>
                        <m:oMath>
                            <m:r><m:t>E = mc^2</m:t></m:r>
                        </m:oMath>
                    </w:p>
                    <w:p>
                        <w:r>
                            <w:t>Second paragraph contents.</w:t>
                        </w:r>
                    </w:p>
                </w:body>
            </w:document>';
            $zip->addFromString('word/document.xml', $xml);
            $zip->close();
        }

        try {
            $parser = new DocumentParser;
            $extractedText = $parser->parse($tempDocx);

            $this->assertStringContainsString('Hello world, this is a test docx file.', $extractedText);
            $this->assertStringContainsString('Second paragraph contents.', $extractedText);
        } finally {
            if (file_exists($tempDocx)) {
                unlink($tempDocx);
            }
        }
    }

    /**
     * Test fetching and saving RAG embedding configurations.
     */
    public function test_can_retrieve_and_save_decoupled_rag_embedding_config(): void
    {
        // Check that initial render works with defaults
        $response = $this->get(route('settings.files'));
        $response->assertStatus(200);
        $response->assertSee('RAG EMBEDDING CONFIGURATION');

        // Post updates to decoupled config
        $response = $this->post(route('settings.files.embedding-config'), [
            'rag_embedding_provider' => 'lmstudio',
            'rag_embedding_model' => 'text-embedding-embeddinggemma-300m',
        ]);

        $response->assertJson([
            'success' => true,
            'message' => 'RAG embedding configurations updated successfully.',
        ]);

        $this->assertEquals('lmstudio', GlobalSetting::getValue('rag_embedding_provider'));
        $this->assertEquals('text-embedding-embeddinggemma-300m', GlobalSetting::getValue('rag_embedding_model'));

        // Assert view sees the new values
        $response = $this->get(route('settings.files'));
        $response->assertStatus(200);
        $response->assertSee('lmstudio');
        $response->assertSee('text-embedding-embeddinggemma-300m');
    }
}
