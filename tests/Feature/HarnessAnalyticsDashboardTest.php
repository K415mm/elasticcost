<?php

namespace Tests\Feature;

use App\Ai\Analytics\LaravelAnalyticsCollector;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Phpkaiharness\Monitor\MonitorReport;
use Phpkaiharness\Monitor\SqliteMonitorStore;
use Phpkaiharness\Support\TraceEvaluator;
use Tests\TestCase;

class HarnessAnalyticsDashboardTest extends TestCase
{
    use RefreshDatabase;

    private string $testDbPath;

    protected function setUp(): void
    {
        parent::setUp();
        // Use a temporary database file for tests
        $this->testDbPath = tempnam(sys_get_temp_dir(), 'harness_test_');
        config(['harness.cache.db_path' => $this->testDbPath]);
        // Disable session isolation so tests read from the temp DB, not per-session folders
        config(['harness.session_isolation.enabled' => false]);
    }

    protected function tearDown(): void
    {
        if (file_exists($this->testDbPath)) {
            @unlink($this->testDbPath);
        }
        parent::tearDown();
    }

    /**
     * Test LaravelAnalyticsCollector writes data to the SQLite database successfully.
     */
    public function test_analytics_collector_logs_to_database(): void
    {
        $collector = new LaravelAnalyticsCollector;
        $sessionId = 'test-session-123';

        // 1. Start session
        $collector->startSession($sessionId, 'Verify ping', 'fast-path-keyword');

        $report = new MonitorReport($this->testDbPath);
        $session = $report->getSession($sessionId);

        $this->assertNotNull($session);
        $this->assertEquals('Verify ping', $session['prompt']);
        $this->assertEquals('fast-path-keyword', $session['method']);

        // 2. Record LLM call
        $collector->recordLlmCall(
            $sessionId,
            'test-model',
            ['input' => 'test'],
            ['output' => 'result'],
            150,
            ['prompt_tokens' => 10, 'completion_tokens' => 20]
        );

        $session = $report->getSession($sessionId);
        $this->assertCount(1, $session['details']);
        $detail = $session['details'][0];
        $this->assertEquals('llm_call', $detail['type']);
        $this->assertEquals('test-model', $detail['name']);
        $this->assertEquals(150, $detail['duration_ms']);
        $this->assertEquals(10, $detail['tokens_prompt']);
        $this->assertEquals(20, $detail['tokens_completion']);

        // 3. Record Tool call
        $collector->recordToolCall($sessionId, 'test_tool', ['arg' => 1], 'output_data', 80);

        $session = $report->getSession($sessionId);
        $this->assertCount(2, $session['details']);
        $detail = $session['details'][1];
        $this->assertEquals('tool_call', $detail['type']);
        $this->assertEquals('test_tool', $detail['name']);
        $this->assertEquals(80, $detail['duration_ms']);

        // 4. End session
        $collector->endSession($sessionId, 'Final text', 230, 2);

        $session = $report->getSession($sessionId);
        $this->assertEquals('Final text', $session['response']);
        $this->assertEquals(230, $session['total_duration_ms']);
        $this->assertEquals(2, $session['iterations']);
    }

    /**
     * Test dashboard page loading.
     */
    public function test_dashboard_page_loads_successfully(): void
    {
        $store = new SqliteMonitorStore($this->testDbPath);
        $store->startSession('session-abc', 'Query system', 'router-classified-action');
        $store->endSession('session-abc', 'Active clients list', 400, 1);

        $response = $this->get(route('harness.dashboard'));
        $response->assertStatus(200);
        $response->assertSee('phpkaiharness');
    }

    /**
     * Test details endpoint returns correct JSON format.
     */
    public function test_details_endpoint_returns_json_details(): void
    {
        $store = new SqliteMonitorStore($this->testDbPath);
        $store->startSession('session-xyz', 'Query system', 'router-classified-action');

        $store->recordToolCall('session-xyz', 'nmap', ['target' => 'localhost'], 'ok', 100);

        $response = $this->get(route('harness.api.session.show', 'session-xyz'));
        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'data' => [
                'id' => 'session-xyz',
                'details' => [
                    [
                        'name' => 'nmap',
                        'type' => 'tool_call',
                    ],
                ],
            ],
        ]);
    }

    /**
     * Test catch-all api endpoint handles AJAX actions.
     */
    public function test_catch_all_api_endpoint_handles_ajax_actions(): void
    {
        $store = new SqliteMonitorStore($this->testDbPath);
        $store->startSession('session-xyz', 'Query system', 'router-classified-action');
        $store->endSession('session-xyz', 'Active clients list', 400, 1);

        // 1. ?action=stats
        $response = $this->get(route('harness.api', ['action' => 'stats']));
        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'stats',
            'daily',
        ]);

        // 2. ?action=sessions
        $response = $this->get(route('harness.api', ['action' => 'sessions', 'limit' => 10]));
        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'sessions',
            'total',
        ]);
        $this->assertEquals(1, $response->json('total'));

        // 3. ?action=session
        $response = $this->get(route('harness.api', ['action' => 'session', 'id' => 'session-xyz']));
        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'session' => [
                'id' => 'session-xyz',
                'prompt' => 'Query system',
            ],
        ]);

        // 4. ?action=agents
        $response = $this->get(route('harness.api', ['action' => 'agents']));
        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'agents',
        ]);

        // 5. ?action=models
        $response = $this->get(route('harness.api', ['action' => 'models', 'provider' => 'gemini']));
        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'models' => [
                'gemini-1.5-flash',
                'gemini-1.5-pro',
                'gemini-2.0-flash',
                'gemini-2.5-flash',
                'gemini-2.5-pro',
            ],
        ]);
    }

    public function test_trace_endpoint_returns_hierarchy_metadata_and_details(): void
    {
        $store = new SqliteMonitorStore($this->testDbPath);
        $store->startSession(
            'int-child-1',
            'Trace this prompt',
            'executor-loop',
            'phpsess-root-1',
            3,
            'phpsess-root-1',
            'req-abc-1',
            'interaction'
        );
        $store->recordEvent(
            'int-child-1',
            'bootstrap',
            'AgentLoop',
            ['request_id' => 'req-abc-1'],
            json_encode(['status' => 'started'], JSON_THROW_ON_ERROR)
        );
        $store->endSession('int-child-1', 'Trace response', 321, 1);

        $response = $this->get(route('harness.api', ['action' => 'trace', 'id' => 'req-abc-1']));

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('state', 'completed')
            ->assertJsonPath('session.id', 'int-child-1')
            ->assertJsonPath('session.parent_session_id', 'phpsess-root-1')
            ->assertJsonPath('session.root_session_id', 'phpsess-root-1')
            ->assertJsonPath('session.request_id', 'req-abc-1')
            ->assertJsonPath('session.interaction_index', 3)
            ->assertJsonPath('session.details.0.type', 'bootstrap');
    }

    public function test_trace_endpoint_returns_pending_for_running_empty_trace(): void
    {
        $store = new SqliteMonitorStore($this->testDbPath);
        $store->startSession('int-pending-1', 'Pending prompt', 'executor-loop', 'phpsess-root-2', 1);

        $response = $this->get(route('harness.api', ['action' => 'trace', 'id' => 'int-pending-1']));

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('state', 'pending')
            ->assertJsonPath('session.id', 'int-pending-1');
    }

    public function test_trace_evaluator_flags_missing_llm_call_as_warn(): void
    {
        $store = new SqliteMonitorStore($this->testDbPath);
        $store->startSession('int-no-llm-1', 'No LLM call', 'executor-loop', 'phpsess-root-3', 1);
        $store->recordEvent('int-no-llm-1', 'bootstrap', 'AgentLoop', [], '{}');
        $store->endSession('int-no-llm-1', 'Some response', 100, 1);

        $evaluator = new TraceEvaluator;
        $result = $evaluator->evaluateSession('int-no-llm-1');

        $this->assertNotNull($result);
        $assertionNodes = array_filter($result['evaluation'], fn ($n) => $n['node_type'] === 'assertion');
        $this->assertNotEmpty($assertionNodes);
        $llmCheck = array_filter($assertionNodes, fn ($n) => $n['name'] === 'llm_call_check');
        $this->assertNotEmpty($llmCheck);
        $this->assertEquals('WARN', array_values($llmCheck)[0]['status']);
    }

    public function test_trace_evaluator_warns_for_enabled_features_without_telemetry(): void
    {
        config([
            'harness.guardrails.enabled' => true,
            'harness.feature_graph.nodes.guardrails.enabled' => true,
        ]);

        $store = new SqliteMonitorStore($this->testDbPath);
        $store->startSession('int-no-guard-1', 'No guardrail', 'executor-loop', 'phpsess-root-4', 1);
        $store->recordLlmCall('int-no-guard-1', 'test-model', ['input' => 'test'], ['output' => 'ok'], 50, []);
        $store->endSession('int-no-guard-1', 'Response', 50, 1);

        $evaluator = new TraceEvaluator;
        $result = $evaluator->evaluateSession('int-no-guard-1');

        $this->assertNotNull($result);
        $guardNodes = array_filter($result['evaluation'], fn ($n) => $n['node_type'] === 'guardrail');
        $this->assertNotEmpty($guardNodes);
        $this->assertEquals('WARN', array_values($guardNodes)[0]['status']);
    }

    public function test_trace_evaluator_includes_hierarchy_metadata_in_report(): void
    {
        $store = new SqliteMonitorStore($this->testDbPath);
        $store->startSession(
            'int-hierarchy-1',
            'Hierarchy test',
            'executor-loop',
            'phpsess-root-5',
            2,
            'phpsess-root-5',
            'req-hierarchy-1',
            'interaction'
        );
        $store->recordLlmCall('int-hierarchy-1', 'test-model', ['input' => 'test'], ['output' => 'ok'], 50, []);
        $store->endSession('int-hierarchy-1', 'Response', 50, 1);

        $evaluator = new TraceEvaluator;
        $result = $evaluator->evaluateSession('int-hierarchy-1');

        $this->assertNotNull($result);
        $this->assertEquals('phpsess-root-5', $result['parent_session_id']);
        $this->assertEquals('phpsess-root-5', $result['root_session_id']);
        $this->assertEquals('req-hierarchy-1', $result['request_id']);
        $this->assertEquals('interaction', $result['session_type']);
        $this->assertEquals(2, $result['interaction_index']);
        $this->assertStringContainsString('Parent Session: phpsess-root-5', $result['report']);
        $this->assertStringContainsString('Request ID: req-hierarchy-1', $result['report']);
    }

    public function test_catch_all_api_endpoint_handles_main_sessions_and_filtered_sessions(): void
    {
        $store = new SqliteMonitorStore($this->testDbPath);
        $store->startSession('session-1', 'Prompt 1', 'executor-loop', 'main-sess-1', 1, 'main-sess-1', 'req-1', 'interaction');
        $store->endSession('session-1', 'Resp 1', 100, 1);
        $store->startSession('session-2', 'Prompt 2', 'fast-path-keyword', 'main-sess-1', 2, 'main-sess-1', 'req-2', 'interaction');
        $store->endSession('session-2', 'Resp 2', 200, 1);

        // Request ?action=main_sessions
        $response = $this->get(route('harness.api', ['action' => 'main_sessions']));
        $response->assertStatus(200);
        $response->assertJsonPath('success', true);
        $response->assertJsonStructure([
            'success',
            'sessions',
            'total',
        ]);
        $sessions = $response->json('sessions');
        $this->assertCount(1, $sessions);
        $this->assertEquals('main-sess-1', $sessions[0]['php_session_id']);
        $this->assertEquals(2, $sessions[0]['sub_session_count']);

        // Request ?action=sessions with php_session_id
        $response = $this->get(route('harness.api', [
            'action' => 'sessions',
            'php_session_id' => 'main-sess-1',
        ]));
        $response->assertStatus(200);
        $data = $response->json('data') ?? $response->json('sessions');
        $this->assertCount(2, $data);
        $ids = array_column($data, 'id');
        $this->assertContains('session-1', $ids);
        $this->assertContains('session-2', $ids);
    }
}
