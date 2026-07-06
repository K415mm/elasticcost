<?php

namespace Tests\Feature;

use App\Ai\Agents\ElasticCostAssistant;
use App\Ai\Agents\RgSocEngineer;
use App\Ai\Agents\RgSocEngineerMain;
use App\Ai\Agents\SocEngineerRouter;
use App\Ai\Tools\CreateClientTool;
use App\Ai\Tools\GetClientInventoryTool;
use App\Ai\Tools\GetSystemDetailsTool;
use App\Ai\Tools\ModifyClientAssetAgentsTool;
use App\Ai\Tools\UpdateAnalystAllocationTool;
use App\Ai\Tools\UpdateGlobalSettingTool;
use App\Models\AgentConversation;
use App\Models\AssetType;
use App\Models\Client;
use App\Models\ClientAsset;
use App\Models\ClientScenarioAnalystAllocation;
use App\Models\ClientScenarioMsspDetail;
use App\Models\GlobalSetting;
use App\Models\Scenario;
use App\Models\SocRole;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\CanActAsTool;
use Laravel\Ai\Contracts\HasTools;
use Laravel\Ai\Responses\Data\Meta;
use Laravel\Ai\Responses\Data\Usage;
use Laravel\Ai\Responses\StructuredAgentResponse;
use Laravel\Ai\Tools\Request;
use Tests\TestCase;

class RgSocEngineerTest extends TestCase
{
    use RefreshDatabase;

    protected $client;

    protected $scenario;

    protected $assetType;

    protected $role;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        $user = User::factory()->ceo()->create();
        $this->actingAs($user);

        $this->client = Client::create([
            'name' => 'Acme Test Client',
            'description' => 'A test client',
        ]);

        $this->scenario = Scenario::first();
        $this->assetType = AssetType::first();
        $this->role = SocRole::first();
    }

    public function test_agent_implements_required_contracts(): void
    {
        $agent = new RgSocEngineer;
        $this->assertInstanceOf(Agent::class, $agent);
        $this->assertInstanceOf(HasTools::class, $agent);

        $instructions = $agent->instructions();
        $this->assertStringContainsString('RG SOC Engineer', $instructions);
        $this->assertStringContainsString('Light Router', $instructions);

        $tools = $agent->tools();
        $this->assertCount(1, $tools);
        $this->assertInstanceOf(RgSocEngineerMain::class, $tools[0]);
    }

    public function test_main_agent_implements_required_contracts(): void
    {
        $mainAgent = new RgSocEngineerMain;
        $this->assertInstanceOf(Agent::class, $mainAgent);
        $this->assertInstanceOf(HasTools::class, $mainAgent);
        $this->assertInstanceOf(CanActAsTool::class, $mainAgent);

        $this->assertEquals('execute_action', $mainAgent->name());
        $this->assertStringContainsString('database access', $mainAgent->description());

        $tools = $mainAgent->tools();
        $this->assertCount(7, $tools);
    }

    public function test_get_system_details_tool(): void
    {
        GlobalSetting::updateOrCreate(['key' => 'siem_agent_monthly_cost_per_device'], ['value' => '15.5']);

        $tool = new GetSystemDetailsTool;
        $result = $tool->handle(new Request([]));

        $data = json_decode($result, true);
        $this->assertArrayHasKey('settings', $data);
        $this->assertEquals('15.5', $data['settings']['siem_agent_monthly_cost_per_device']);
        $this->assertNotEmpty($data['clients']);
        $this->assertNotEmpty($data['scenarios']);
        $this->assertNotEmpty($data['soc_roles']);
    }

    public function test_update_global_setting_tool(): void
    {
        $tool = new UpdateGlobalSettingTool;
        $response = $tool->handle(new Request([
            'key' => 'custom_setting_key',
            'value' => 'custom_value_123',
            'description' => 'Test setting',
        ]));

        $this->assertStringContainsString("Successfully updated setting 'custom_setting_key' to 'custom_value_123'", $response);
        $this->assertEquals('custom_value_123', GlobalSetting::getValue('custom_setting_key'));
    }

    public function test_get_client_inventory_tool(): void
    {
        $asset = ClientAsset::firstOrCreate([
            'client_id' => $this->client->id,
            'asset_type_id' => $this->assetType->id,
        ], [
            'device_count' => 10,
        ]);

        $tool = new GetClientInventoryTool;
        $result = $tool->handle(new Request(['client_id' => $this->client->id]));

        $data = json_decode($result, true);
        $this->assertNotEmpty($data);
        $this->assertEquals($this->assetType->name, $data[0]['asset_type_name']);
    }

    public function test_modify_client_asset_agents_tool(): void
    {
        $asset = ClientAsset::firstOrCreate([
            'client_id' => $this->client->id,
            'asset_type_id' => $this->assetType->id,
        ], [
            'device_count' => 5,
        ]);

        $tool = new ModifyClientAssetAgentsTool;
        $response = $tool->handle(new Request([
            'client_id' => $this->client->id,
            'asset_id' => $asset->id,
            'runs_siem_agent' => true,
            'runs_mdr_agent' => true,
            'runs_edr_agent' => false,
        ]));

        $this->assertStringContainsString('Successfully updated 1 assets', $response);

        $asset->refresh();
        $this->assertTrue($asset->runs_siem_agent);
        $this->assertTrue($asset->runs_mdr_agent);
        $this->assertFalse($asset->runs_edr_agent);
    }

    public function test_update_analyst_allocation_tool(): void
    {
        $detail = ClientScenarioMsspDetail::firstOrCreate([
            'client_id' => $this->client->id,
            'scenario_id' => $this->scenario->id,
        ]);

        $tool = new UpdateAnalystAllocationTool;
        $response = $tool->handle(new Request([
            'client_id' => $this->client->id,
            'scenario_id' => $this->scenario->id,
            'soc_role_id' => $this->role->id,
            'allocation_percentage' => 25.50,
            'custom_monthly_salary' => 5000.00,
            'staff_count' => 2,
        ]));

        $this->assertStringContainsString('Successfully updated SOC role ID', $response);

        $allocation = ClientScenarioAnalystAllocation::where([
            'mssp_details_id' => $detail->id,
            'soc_role_id' => $this->role->id,
        ])->first();

        $this->assertNotNull($allocation);
        $this->assertEquals(25.50, (float) $allocation->allocation_percentage);
        $this->assertEquals(5000.00, (float) $allocation->custom_monthly_salary);
        $this->assertEquals(2, $allocation->staff_count);
    }

    public function test_chat_controller_routes_to_engineer_agent_correctly(): void
    {
        RgSocEngineer::fake(['Mock Engineer response details']);

        $postData = [
            'message' => 'Change the SIEM agent price to 20',
            'agent' => 'RgSocEngineer',
        ];

        $response = $this->post(route('ai-chat.message'), $postData);
        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'queued' => true,
        ]);

        $this->assertDatabaseHas('agent_conversation_messages', [
            'role' => 'user',
            'agent' => 'RgSocEngineer',
            'content' => 'Change the SIEM agent price to 20',
        ]);

        $this->assertDatabaseHas('agent_conversation_messages', [
            'role' => 'assistant',
            'agent' => 'RgSocEngineer',
        ]);
    }

    public function test_chat_controller_applies_sliding_window_and_pruning(): void
    {
        $conversation = AgentConversation::create(['title' => 'Long conversation']);

        // Create 8 historical messages (some out of window, some in window, one very long)
        $conversation->messages()->create(['role' => 'user', 'content' => 'Message 1 out of window', 'agent' => 'ElasticCostAssistant']);
        $conversation->messages()->create(['role' => 'assistant', 'content' => 'Message 2 out of window', 'agent' => 'ElasticCostAssistant']);

        $conversation->messages()->create(['role' => 'user', 'content' => 'Message 3 in window', 'agent' => 'ElasticCostAssistant']);
        $conversation->messages()->create(['role' => 'assistant', 'content' => 'Message 4 in window', 'agent' => 'ElasticCostAssistant']);
        $conversation->messages()->create(['role' => 'user', 'content' => 'Message 5 in window', 'agent' => 'ElasticCostAssistant']);
        $conversation->messages()->create(['role' => 'assistant', 'content' => 'Message 6 in window', 'agent' => 'ElasticCostAssistant']);

        // A very long message that should get pruned
        $longContent = str_repeat('A', 2000);
        $conversation->messages()->create(['role' => 'user', 'content' => $longContent, 'agent' => 'ElasticCostAssistant']);

        // Fake the AI agent response
        ElasticCostAssistant::fake(['Mock response']);

        $response = $this->post(route('ai-chat.message', $conversation->id), [
            'message' => 'Latest message',
            'agent' => 'ElasticCostAssistant',
        ]);

        $response->assertStatus(200);

        $this->assertDatabaseHas('agent_conversation_messages', [
            'conversation_id' => $conversation->id,
            'role' => 'user',
            'content' => 'Latest message',
        ]);
    }

    public function test_chat_controller_queues_conversational_chat_response_asynchronously(): void
    {
        RgSocEngineer::fake(['The SIEM price is currently $15 per device.']);

        $postData = [
            'message' => 'What is the price of SIEM?',
            'agent' => 'RgSocEngineer',
        ];

        $response = $this->post(route('ai-chat.message'), $postData);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'queued' => true,
        ]);

        $this->assertDatabaseHas('agent_conversation_messages', [
            'role' => 'assistant',
            'agent' => 'RgSocEngineer',
        ]);
    }

    public function test_chat_controller_returns_greetings_instantly_without_llm(): void
    {
        $postData = [
            'message' => 'Hello',
            'agent' => 'RgSocEngineer',
        ];

        $response = $this->post(route('ai-chat.message'), $postData);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'queued' => false,
        ]);

        $this->assertDatabaseHas('agent_conversation_messages', [
            'role' => 'assistant',
            'agent' => 'RgSocEngineer',
            'content' => 'Hello! I am the **RG SOC Engineer**. I can help you inspect system details, modify global settings, enable/disable security agent coverage on assets, update device counts, or manage analyst allocations. What would you like to do today?',
        ]);
    }

    public function test_parent_routing_programmatic_delegation_when_action_required(): void
    {
        // Fake the router to return a structured response indicating action is required
        SocEngineerRouter::fake([
            new StructuredAgentResponse(
                'router-id',
                [
                    'requires_action' => true,
                    'action_instruction' => 'List all clients',
                    'chat_response' => '',
                ],
                'Requires action',
                new Usage,
                new Meta
            ),
        ]);

        // Fake the main agent to return the database details
        RgSocEngineerMain::fake(['Client A, Client B']);

        $agent = new RgSocEngineer;
        $response = $agent->prompt('process indexing');

        $this->assertEquals('Client A, Client B', $response->text);

        // Assert that the router was prompted with the user's request
        SocEngineerRouter::assertPrompted(function ($prompt) {
            return str_contains($prompt->prompt, 'process indexing');
        });

        // Assert that the main agent was prompted with the instruction from the router
        RgSocEngineerMain::assertPrompted(function ($prompt) {
            return str_contains($prompt->prompt, 'List all clients');
        });
    }

    public function test_parent_routing_programmatic_delegation_when_no_action_required(): void
    {
        // Fake the router to return a structured response indicating no action is required
        SocEngineerRouter::fake([
            new StructuredAgentResponse(
                'router-id',
                [
                    'requires_action' => false,
                    'action_instruction' => '',
                    'chat_response' => 'Hello there! How can I help you today?',
                ],
                'No action required',
                new Usage,
                new Meta
            ),
        ]);

        $agent = new RgSocEngineer;
        $response = $agent->prompt('hello');

        $this->assertEquals('Hello there! How can I help you today?', $response->text);

        // Assert that the main agent was never prompted
        RgSocEngineerMain::assertNeverPrompted();
    }

    public function test_get_system_details_tool_includes_asset_types(): void
    {
        $tool = new GetSystemDetailsTool;
        $result = $tool->handle(new Request([]));

        $data = json_decode($result, true);
        $this->assertArrayHasKey('asset_types', $data);
        $this->assertNotEmpty($data['asset_types']);
        $this->assertEquals('Active Directory', $data['asset_types'][0]['name']);
    }

    public function test_create_client_tool(): void
    {
        $tool = new CreateClientTool;

        $deviceCounts = json_encode([
            '1' => 120, // Active Directory
            '2' => 50,  // FortiGate Firewalls
        ]);

        $agentCoverages = json_encode([
            '1' => [
                'runs_siem_agent' => true,
                'runs_mdr_agent' => true,
                'runs_edr_agent' => false,
            ],
        ]);

        $response = $tool->handle(new Request([
            'name' => 'Brand New Client LLC',
            'description' => 'A fully customized client description',
            'device_counts' => $deviceCounts,
            'agent_coverages' => $agentCoverages,
        ]));

        $this->assertStringContainsString("Successfully created client 'Brand New Client LLC'", $response);

        // Verify database records
        $client = Client::where('name', 'Brand New Client LLC')->first();
        $this->assertNotNull($client);
        $this->assertEquals('A fully customized client description', $client->description);

        $clientAssets = ClientAsset::where('client_id', $client->id)->get();
        // Since we have 6 seeded asset types, there should be 6 ClientAsset records
        $this->assertCount(6, $clientAssets);

        // Active Directory (Asset Type ID: 1) should have count 120 and customized coverages
        $adAsset = $clientAssets->where('asset_type_id', 1)->first();
        $this->assertNotNull($adAsset);
        $this->assertEquals(120, $adAsset->device_count);
        $this->assertTrue($adAsset->runs_siem_agent);
        $this->assertTrue($adAsset->runs_mdr_agent);
        $this->assertFalse($adAsset->runs_edr_agent);

        // FortiGate Firewalls (Asset Type ID: 2) should have count 50 and default coverages
        $fortiAsset = $clientAssets->where('asset_type_id', 2)->first();
        $this->assertNotNull($fortiAsset);
        $this->assertEquals(50, $fortiAsset->device_count);
        $this->assertTrue($fortiAsset->runs_siem_agent);
        $this->assertTrue($fortiAsset->runs_mdr_agent);
        $this->assertFalse($fortiAsset->runs_edr_agent);

        // Others should have count 0
        $winAsset = $clientAssets->where('asset_type_id', 4)->first();
        $this->assertNotNull($winAsset);
        $this->assertEquals(0, $winAsset->device_count);
    }
}
