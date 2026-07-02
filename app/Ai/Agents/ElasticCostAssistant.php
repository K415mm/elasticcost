<?php

namespace App\Ai\Agents;

use App\Ai\Middleware\InjectDocumentation;
use Laravel\Ai\Attributes\Model;
use Laravel\Ai\Attributes\Provider;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasMiddleware;
use Laravel\Ai\Contracts\HasTools;
use Laravel\Ai\Enums\Lab;
use Laravel\Ai\Promptable;
use Stringable;

#[Provider(Lab::Ollama)]
#[Model('gemma4:e2b')]
class ElasticCostAssistant implements Agent, HasMiddleware, HasTools
{
    use Promptable;

    public ?string $phpSessionId = null;

    /**
     * Get the agent's prompt middleware.
     */
    public function middleware(): array
    {
        return [
            new InjectDocumentation,
        ];
    }

    /**
     * Get the tools available to the agent.
     */
    public function tools(): iterable
    {
        return [
            new RgSocEngineer,
            new OfferAnalyst,
        ];
    }

    /**
     * Get the instructions that the agent should follow.
     */
    public function instructions(): Stringable|string
    {
        return <<<'INSTRUCTIONS'
You are the "ElasticCost Assistant", an elite AI chatbot built into the ElasticCost Sizing & Costing Calculator.

Your goal is to answer queries from systems architects, SOC managers, and financial analysts regarding:
1. **Elasticsearch Sizing**: RAM-to-disk ratios (Hot 1:30, Warm 1:80, Cold 1:100, Frozen 1:160), index metadata expansion (1.25x), replicas, shard sizes (30-50GB), and JVM Heap constraints.
2. **MSSP SOC Costing**: Staffing pricing (L1, L2, L3, Engineer, Manager), hosting cards (RAM/SSD unit prices), assurance markup benefits, and offered Client MRC calculation.
3. **Application Features**: Sizing Dashboard, MSSP Cost Proposal dashboard, excel/word/markdown exports, currency conversions (USD/EUR/TND), and translation overrides.

---

## CONVERSATIONAL RULES
- Keep your answers concise, structured, and professional.
- Use Markdown formatting (bold, tables, lists, code blocks) to make information readable.
- If the user asks you to size a cluster, ask them for details such as daily log volume, active device counts, or retention requirements.
- Never output raw JSON configurations in a messy way.
- Respond in the user's language (English, French, or Arabic).
INSTRUCTIONS;
    }
}
