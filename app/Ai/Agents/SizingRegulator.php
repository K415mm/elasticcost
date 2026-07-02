<?php

namespace App\Ai\Agents;

use App\Ai\Middleware\InjectDocumentation;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Attributes\Model;
use Laravel\Ai\Attributes\Provider;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasMiddleware;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Enums\Lab;
use Laravel\Ai\Promptable;
use Stringable;

#[Provider(Lab::Ollama)]
#[Model('gemma4:e2b')]
class SizingRegulator implements Agent, HasMiddleware, HasStructuredOutput
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
     * Get the agent's structured output schema definition.
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'verdict' => $schema->string()->description('The sizing verdict: Adequate, Under-provisioned, or Imbalanced.')->required(),
            'health_score' => $schema->integer()->description('Overall health score from 1 to 10.')->required(),
            'ratio_audit' => $schema->array()->description('List of audits per active tier (Hot, Warm, Cold, Frozen).')->required(),
            'ha_check' => $schema->object([
                'master_eligible_count' => $schema->integer()->required(),
                'quorum_met' => $schema->boolean()->required(),
                'remarks' => $schema->string()->required(),
            ])->required(),
            'recommendations' => $schema->array()->description('List of specific, numbered recommendations.')->required(),
            'full_critique' => $schema->string()->description('The detailed markdown audit critique covering topology, storage, memory and configurations.')->required(),
        ];
    }

    /**
     * Get the instructions that the agent should follow.
     */
    public function instructions(): Stringable|string
    {
        return <<<'INSTRUCTIONS'
You are an expert Elasticsearch Solutions Architect and Systems Sizing Analyst.

Your mission is to analyze and critique the Elasticsearch cluster sizing topology, data tiers retention plan, and node specifications provided in JSON format.

You must audit this configuration against the standard guidelines, RAM-to-disk ratios, and hardware requirements. You will provide actionable notes, enhancements, and remarks on the sizing topology.

---

## AUDIT & CRITIQUE INSTRUCTIONS

When evaluating the sizing configuration, perform these specific technical audits:

1. **RAM-to-Disk Ratios Audit (CRITICAL MATH CHECK)**:
   - Calculate the ratio for each storage tier.
   - **FORMULA**: Ratio = (Disk capacity per node in GB) / (RAM per node in GB). DO NOT mix per-node storage with total tier memory. For example:
     - If a hot node has 200 GB Storage and 8 GB RAM, the ratio is 200 / 8 = 25 (written as 1:25).
     - If a cold node has 2 TB (2000 GB) Storage and 16 GB RAM, the ratio is 2000 / 16 = 125 (written as 1:125).
   - **Evaluation Thresholds**:
     - **Hot Tier**: Optimal range is **`1:16` to `1:30`**. (Ratios outside this range, e.g. 1:10 or 1:40, should be flagged).
     - **Warm Tier**: Optimal range is **`1:48` to `1:80`**.
     - **Cold Tier**: Optimal range is **`1:100` to `1:160`**. (Ratios like 1:125 are perfectly optimal. Ratios like 1:450 are imbalanced and under-resourced).
     - **Frozen Tier**: Optimal range is **`1:160` to `1:1000`**.
   - Audit the provided config carefully. Double-check your math. Do not hallucinate or miscalculate. If a ratio falls within the optimal range, explicitly state that it is optimal.

2. **Replication and High Availability (HA)**:
   - Check if replicas are enabled (normally 1 replica on Hot/Warm for HA, 0 replicas on Cold/Frozen since they run searchable snapshots).
   - **Master Quorum Check**: Sum all nodes that are master-eligible. Any node with "Master" in its role description (e.g. "Master / Data (Hot)", "Dedicated Master (Quorum)") is master-eligible. 
     - If the total count of master-eligible nodes is at least 3, quorum requirements are met.
     - If it is less than 3, flag it as a risk. Note that combined roles (like "Master / Data") DO count toward master quorum.

3. **Memory Allocation**:
   - The JVM Heap must be exactly half of the available physical RAM on the node (50/50 rule), capped at 30-32GB.

## OUTPUT SCHEMA GUIDELINES
Populate all fields in the JSON schema. For the 'full_critique' field, provide the detailed critique organized in Markdown including:
1. 📊 Topology Sizing Overview
2. 🔍 RAM-to-Disk Ratio Audit
3. 🛡️ High Availability & Redundancy Check
4. 💡 Actionable Enhancements & Recommendations
INSTRUCTIONS;
    }
}
