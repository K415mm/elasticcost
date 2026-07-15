<?php

namespace Tests\Feature;

use App\Ai\Tools\CreateDrawioDiagramTool;
use App\Ai\Tools\ViewDrawioDiagramsTool;
use App\Models\Client;
use App\Models\Diagram;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Ai\Tools\Request as ToolRequest;
use Tests\TestCase;

class DiagramTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected Client $client;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        $this->user = User::factory()->ceo()->create();
        $this->actingAs($this->user);

        $this->client = Client::create([
            'name' => 'Acme Test Corp',
            'description' => 'Test client description',
        ]);
    }

    /**
     * Test index page shows client diagrams list.
     */
    public function test_can_view_diagrams_index(): void
    {
        $response = $this->get(route('clients.diagrams.index', $this->client->id));
        $response->assertOk();
        $response->assertViewIs('clients.diagrams.index');
        $response->assertSee('Client Diagrams List');
    }

    /**
     * Test storing a diagram.
     */
    public function test_can_store_diagram(): void
    {
        $payload = [
            'name' => 'SOC Network Architecture',
            'type' => 'network_diagram',
            'content' => '<mxfile><diagram>Test XML</diagram></mxfile>',
        ];

        $response = $this->post(route('clients.diagrams.store', $this->client->id), $payload);

        $diagram = Diagram::first();
        $this->assertNotNull($diagram);
        $this->assertEquals('SOC Network Architecture', $diagram->name);
        $this->assertEquals('network_diagram', $diagram->type);
        $this->assertEquals('<mxfile><diagram>Test XML</diagram></mxfile>', $diagram->content);

        $response->assertRedirect(route('clients.diagrams.show', [$this->client->id, $diagram->id]));
    }

    /**
     * Test updating a diagram.
     */
    public function test_can_update_diagram(): void
    {
        $diagram = Diagram::create([
            'client_id' => $this->client->id,
            'name' => 'Initial Diagram',
            'type' => 'custom',
            'content' => '<initial></initial>',
        ]);

        $payload = [
            'content' => '<updated></updated>',
        ];

        $response = $this->put(route('clients.diagrams.update', [$this->client->id, $diagram->id]), $payload);

        $diagram->refresh();
        $this->assertEquals('<updated></updated>', $diagram->content);
        $response->assertRedirect(route('clients.diagrams.show', [$this->client->id, $diagram->id]));
    }

    /**
     * Test deleting a diagram.
     */
    public function test_can_delete_diagram(): void
    {
        $diagram = Diagram::create([
            'client_id' => $this->client->id,
            'name' => 'To Delete',
            'type' => 'custom',
            'content' => '<xml></xml>',
        ]);

        $response = $this->delete(route('clients.diagrams.destroy', [$this->client->id, $diagram->id]));

        $this->assertNull(Diagram::find($diagram->id));
        $response->assertRedirect(route('clients.diagrams.index', $this->client->id));
    }

    /**
     * Test CreateDrawioDiagramTool execution.
     */
    public function test_create_drawio_diagram_tool(): void
    {
        $tool = new CreateDrawioDiagramTool;
        $toolRequest = new ToolRequest([
            'client_id' => $this->client->id,
            'diagram_name' => 'AI Generated Diagram',
            'diagram_type' => 'soc_architecture',
            'drawio_xml' => '<ai-xml></ai-xml>',
        ]);

        $resultJson = $tool->handle($toolRequest);
        $result = json_decode($resultJson, true);

        $this->assertEquals('success', $result['status']);
        $this->assertEquals('Diagram created successfully.', $result['message']);

        $diagram = Diagram::where('name', 'AI Generated Diagram')->first();
        $this->assertNotNull($diagram);
        $this->assertEquals('<ai-xml></ai-xml>', $diagram->content);
        $this->assertEquals('soc_architecture', $diagram->type);
    }

    /**
     * Test ViewDrawioDiagramsTool execution.
     */
    public function test_view_drawio_diagrams_tool(): void
    {
        Diagram::create([
            'client_id' => $this->client->id,
            'name' => 'Existing Diagram 1',
            'type' => 'custom',
            'content' => '<xml-1></xml-1>',
        ]);

        $tool = new ViewDrawioDiagramsTool;
        $toolRequest = new ToolRequest([
            'client_id' => $this->client->id,
        ]);

        $resultJson = $tool->handle($toolRequest);
        $result = json_decode($resultJson, true);

        $this->assertEquals('success', $result['status']);
        $this->assertEquals(1, $result['count']);
        $this->assertEquals('Existing Diagram 1', $result['data'][0]['name']);
    }
}
