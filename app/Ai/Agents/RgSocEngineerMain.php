<?php

namespace App\Ai\Agents;

use App\Ai\Tools\CreateClientTool;
use App\Ai\Tools\GetClientInventoryTool;
use App\Ai\Tools\GetSystemDetailsTool;
use App\Ai\Tools\ModifyClientAssetAgentsTool;
use App\Ai\Tools\UpdateAnalystAllocationTool;
use App\Ai\Tools\UpdateClientInventoryTool;
use App\Ai\Tools\UpdateGlobalSettingTool;
use App\Services\AiConfigHelper;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\CanActAsTool;
use Laravel\Ai\Contracts\HasMiddleware;
use Laravel\Ai\Contracts\HasTools;
use Laravel\Ai\Enums\Lab;
use Laravel\Ai\Promptable;
use Phpkaiharness\Http\Middleware\PolicyGuardrailMiddleware;
use Stringable;

class RgSocEngineerMain implements Agent, CanActAsTool, HasMiddleware, HasTools
{
    use Promptable;

    public ?string $phpSessionId = null;

    /**
     * Get the agent's prompt middleware.
     */
    public function middleware(): array
    {
        return [
            new PolicyGuardrailMiddleware,
        ];
    }

    /**
     * Get the name of the tool.
     */
    public function name(): string
    {
        return 'execute_action';
    }

    /**
     * Get the description of the tool's purpose.
     */
    public function description(): Stringable|string
    {
        return 'Delegates a task that requires database access, retrieving current system settings/details, listing clients, verifying status, or making updates.';
    }

    /**
     * Get the runtime configured provider for the Main Model.
     */
    public function provider(): string|Lab
    {
        $config = AiConfigHelper::configureMultiModel();

        return $config['main']['provider'];
    }

    /**
     * Get the runtime configured model for the Main Model.
     */
    public function model(): string
    {
        $config = AiConfigHelper::configureMultiModel();

        return $config['main']['model'];
    }

    /**
     * Get the instructions that the agent should follow.
     */
    public function instructions(): Stringable|string
    {
        return <<<'INSTRUCTIONS'
You are the "RG SOC Engineer Main", the elite database administrator and action execution engine for the ElasticCost platform.
Your job is to execute database lookups, inspect configuration states, and modify system settings based on the task delegated to you by the router.

## CRITICAL CONTEXT RULES
- The CONVERSATION CONTEXT section provides prior messages. Use it to identify which client is being discussed.
- If the user was previously discussing a specific client (e.g., "Acme Corp"), assume they mean that client unless they specify otherwise.
- NEVER ask the user to confirm which client they mean if it's clear from conversation context. Just proceed with the action.
- NEVER report on what you didn't find or couldn't retrieve. Only report the actual results.
- NEVER mention "memory search", "historical notes", "retrieved records", or internal lookup processes in your response.
- Be concise and direct. Execute the task and present the results cleanly.

## OPERATIONAL PLAYBOOK & ACTIONS
1. **Always inspect current state first**: If the task requests an update, check active settings or inventory first (using GetSystemDetailsTool or GetClientInventoryTool).
   - To list all clients, inspect global settings, or view active rates, scenarios, or roles, call GetSystemDetailsTool.
2. **Execute changes directly**:
   - If the task is to set a setting (e.g. SIEM price to 20), use UpdateGlobalSettingTool.
   - If the task is to set device counts (e.g. Active Directory device count to 150 for client X), call GetClientInventoryTool to get the asset_type_id first, then call UpdateClientInventoryTool.
   - If the task is to modify agent assignments, use ModifyClientAssetAgentsTool.
   - If the task is to allocate analysts, use UpdateAnalystAllocationTool.
3. **Conversational Client Creation**:
   - When the user asks to add, create, or register a new client, you must collect:
     a) Client Name
     b) Client Description (optional)
     c) Device counts for all available asset types in the system.
   - First call `GetSystemDetailsTool` to get the list of active `asset_types` and their names.
   - Audit the conversation history. If the name, description, or device counts for any of the active asset types are missing, ask the user to provide them. List the asset types so the user knows what you are asking for.
   - Do NOT invoke `CreateClientTool` with empty or guessed device counts. Ask the user first. You may assume default count of 0 only if the user says so or explicitly declines to configure them now.
   - Once you have the name, description, and device counts, construct a JSON map of `asset_type_id => count` (as a string) and call `CreateClientTool`.
4. **Present the results**: Format the results cleanly as Markdown tables, bullet points, or lists. Be extremely clear about the exact changes made or database values retrieved.
INSTRUCTIONS;
    }

    /**
     * Get the tools available to the agent.
     */
    public function tools(): iterable
    {
        return [
            new GetSystemDetailsTool,
            new UpdateGlobalSettingTool,
            new ModifyClientAssetAgentsTool,
            new GetClientInventoryTool,
            new UpdateClientInventoryTool,
            new UpdateAnalystAllocationTool,
            new CreateClientTool,
        ];
    }

    /**
     * Get the default timeout (in seconds) for the agent.
     */
    public function timeout(): int
    {
        return 300;
    }
}
