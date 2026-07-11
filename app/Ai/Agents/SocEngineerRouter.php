<?php

namespace App\Ai\Agents;

use App\Services\AiConfigHelper;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Enums\Lab;
use Laravel\Ai\Promptable;
use Stringable;

class SocEngineerRouter implements Agent, HasStructuredOutput
{
    use Promptable;

    public ?string $phpSessionId = null;

    /**
     * Get the runtime configured provider for the Light Model.
     */
    public function provider(): string|Lab
    {
        $config = AiConfigHelper::configureMultiModel();

        return $config['light']['provider'];
    }

    /**
     * Get the runtime configured model for the Light Model.
     */
    public function model(): string
    {
        $config = AiConfigHelper::configureMultiModel();

        return $config['light']['model'];
    }

    /**
     * Get the agent's structured output schema definition.
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'requires_action' => $schema->boolean()->description('True if the user request requires database access, retrieving current system state, listing clients, showing settings, or performing modifications. False if it is a general chat, greeting, or conversational question.')->required(),
            'action_instruction' => $schema->string()->description('If requires_action is true, extract the consolidated, clean instruction for the action executor. E.g., "List all clients", "Set SIEM agent price to 20", or "Enable SIEM coverage for client 1".')->required(),
            'chat_response' => $schema->string()->description('If requires_action is false, provide a direct, helpful chat response to the user query.')->required(),
        ];
    }

    /**
     * Get the instructions that the agent should follow.
     */
    public function instructions(): Stringable|string
    {
        return <<<'INSTRUCTIONS'
You are the primary intent classifier and router for the Security Operations Center.
Your task is to analyze the conversation history and specifically focus on the LATEST user message at the very end to classify the user's intent.

### CLASSIFICATION RULES:
1. Set `requires_action` to `true` if the user is asking to:
   - List, show, find, retrieve, query, or inspect any clients, sizing information, global settings, database records, SOC roles, or device counts.
   - Update, change, modify, set, enable, disable, or assign any settings, parameters, or counts.
   - Create, add, register, or set up new clients with their assets or device counts.
   - Use database access, actions, or tools to retrieve data.
   - Example requests: "list all clients", "show sizing", "what is the SIEM price?", "create a new client called Acme", "add client", "change EDR device count to 50 for client X", "use tools to retrieve client details".
   - In this case, extract a clean, consolidated task instruction for the executor in `action_instruction` (e.g. "List all clients", "Set EDR count to 50 for client X").

2. Set `requires_action` to `false` if and only if the user request:
   - Is a simple greeting, pleasantry, or conversational exchange (e.g., "hello", "hi", "hey", "how are you", "thank you").
   - Is a general query that does not require active database details or updates (e.g., "what can you do?", "explain what EDR is").
   - In this case, provide a direct, concise, and helpful conversational response in `chat_response`.

### RESPONSE FORMAT:
Return a valid JSON object matching the requested schema. The word json must appear in this response.

### CRITICAL DIRECTIVE:
If the user's message mentions using database actions, tools, queries, listing records, or checking settings, you MUST classify it as `requires_action = true`. Do not answer these requests conversational-style; always delegate them to the action executor.
INSTRUCTIONS;
    }
}
