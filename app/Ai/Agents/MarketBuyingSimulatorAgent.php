<?php

namespace App\Ai\Agents;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Attributes\Model;
use Laravel\Ai\Attributes\Provider;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Enums\Lab;
use Laravel\Ai\Promptable;
use Stringable;

#[Provider(Lab::Ollama)]
#[Model('gemma4:e2b')]
class MarketBuyingSimulatorAgent implements Agent, HasStructuredOutput
{
    use Promptable;

    public ?string $phpSessionId = null;

    /**
     * Get the agent's structured output schema definition.
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'market_attractiveness_score' => $schema->integer()->description('Overall market buying attractiveness score from 1 to 10.')->required(),
            'buyer_persona_behavior' => $schema->string()->description('Analysis of how enterprise partners and direct clients simulate buying your packages.')->required(),
            'pricing_strategy_feedback' => $schema->string()->description('Assessment of Partner Wholesale vs Client Retail margin competitiveness.')->required(),
            'pack_vs_agent_preference' => $schema->string()->description('Comparison of client preference for standalone unit agents vs bundled custom packs.')->required(),
            'capacity_sold_out_forecast' => $schema->string()->description('Forecast of when EDR/MDR/SIEM capacity will hit 100% Sold Out status.')->required(),
            'optimization_recommendations' => $schema->array()->description('Specific actionable recommendations to maximize cumulative 36-month net profit.')->required(),
            'full_market_report' => $schema->string()->description('Comprehensive markdown report containing market simulation findings, revenue breakdown, and strategic pricing advice.')->required(),
        ];
    }

    /**
     * Get the instructions that the agent should follow.
     */
    public function instructions(): Stringable|string
    {
        return <<<'INSTRUCTIONS'
You are an elite Cybersecurity MSSP (Managed Security Service Provider) Market Buying Simulator & Profit Optimization AI Agent.

Your role is to simulate realistic market purchasing behavior from corporate enterprise clients, MSSP reseller partners, and SMBs buying security agent packages (EDR, MDR, SIEM) and custom service packs (bundled with Threat Intel, SLA, etc.).

When provided with simulation input data (pricing rates, margins, sales velocity, custom service packs, system capacity limits, and 36-month financial projections):

1. **SIMULATE MARKET BUYING PATTERNS**:
   - Evaluate whether pricing is attractive to partner channels (+25% margin target) and direct clients (+50% margin target).
   - Assess if custom service packs (e.g., EDR + CTI) offer higher value perception than standalone unit agents.

2. **ANALYZE CAPACITY & SOLD OUT RISKS**:
   - Check when EDR, MDR, or SIEM agent pools reach their max capacity limits.
   - Highlight the transition from active growth to "SOLD OUT" subscription profit maintenance.

3. **PROVIDE STRATEGIC PROFIT OPTIMIZATION**:
   - Identify underpriced or overpriced packages.
   - Provide concrete, numbered recommendations to increase overall 36-month cumulative net profit.

4. **OUTPUT FORMAT**:
   - Generate structured JSON matching the requested schema.
   - Provide a clear, polished Markdown report in `full_market_report`.
INSTRUCTIONS;
    }
}
