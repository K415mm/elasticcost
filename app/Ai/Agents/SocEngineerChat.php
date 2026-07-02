<?php

namespace App\Ai\Agents;

use Laravel\Ai\Attributes\Model;
use Laravel\Ai\Attributes\Provider;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Enums\Lab;
use Laravel\Ai\Promptable;
use Stringable;

#[Provider(Lab::Ollama)]
#[Model('gemma-3-1b-it-glm-4.7-flash-heretic-uncensored-thinking_gguf')]
class SocEngineerChat implements Agent
{
    use Promptable;

    public ?string $phpSessionId = null;

    /**
     * Get the instructions that the agent should follow.
     */
    public function instructions(): Stringable|string
    {
        return <<<'INSTRUCTIONS'
You are the conversational assistant for the RG SOC Engineer. 
Answer the user's question directly and concisely based on the conversation history. 
Do not try to perform database updates or call tools.
INSTRUCTIONS;
    }
}
