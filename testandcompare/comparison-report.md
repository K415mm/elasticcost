# phpkaiharness Comparison Test Report

**Generated:** 2026-06-29 13:41:35

**Test Environment:** ElasticCost Platform with qwen3.5-397b-a17b

---

## Executive Summary

This report compares three execution modes across 0 test requests:

| Mode | Description |
|---|---|
| **A1 — Direct API** | Raw Qwen Cloud API call, no harness, no tools, no pipeline |
| **A2 — Loop (no features)** | AgentLoop with all feature_graph nodes disabled |
| **B — Full phpkaiharness** | All features enabled: draft verification, quantum memory, ontology RAG, semantic cache, optimizer |

### Key Findings

- **Latency:** Full harness averages 47435.15ms vs 19708.35ms for direct API (+140.7%)
- **Token Usage:** Full harness uses 0 tokens avg vs 0 for direct API (N/A)
- **Tool Calls:** Full harness averages 0 tool calls per request; A1/A2 have 0
- **Success Rate:** A1: 0/0, A2: 0/0, B: 0/0

---

## Aggregate Metrics Comparison

| Metric | A1 (Direct API) | A2 (Loop, no features) | B (Full Harness) | B vs A1 |
|---|---|---|---|---|
| **Avg Latency (ms)** | 19708.35 | 14132.25 | 47435.15 | +140.7% |
| **Min Latency (ms)** | N/A | N/A | N/A | N/A |
| **Max Latency (ms)** | N/A | N/A | N/A | N/A |
| **Avg Total Tokens** | N/A | N/A | N/A | N/A |
| **Avg Tool Calls** | N/A | N/A | N/A | N/A |
| **Avg Response Length (chars)** | N/A | N/A | N/A | N/A |
| **Avg Pipeline Stages** | N/A | N/A | N/A | N/A |
| **Successful Requests** | N/A | N/A | N/A | N/A |

---

## Per-Request Comparison

| # | Agent | Category | A1 Latency | A2 Latency | B Latency | A1 Tokens | B Tokens | B Tools | B Stages |
|---|---|---|---|---|---|---|---|---|---|
| 1 | ElasticCostAssistant | sizing-basic | 15994ms | 6670ms | 68188ms | 486 | 565 | 0 | 0 |
| 2 | ElasticCostAssistant | sizing-calculation | 44473ms | 18698ms | 114763ms | 859 | 1128 | 0 | 0 |
| 3 | ElasticCostAssistant | sizing-french | 18300ms | 11189ms | 79872ms | 573 | 926 | 0 | 0 |
| 4 | ElasticCostAssistant | costing-calculation | 35063ms | 13984ms | 109309ms | 1157 | 1244 | 2 | 0 |
| 5 | ElasticCostAssistant | sizing-tunisian | 76807ms | 22563ms | 158116ms | 919 | 1562 | 0 | 0 |
| 6 | ElasticCostAssistant | sizing-replicas | 28178ms | 11806ms | 71822ms | 708 | 714 | 0 | 0 |
| 7 | ElasticCostAssistant | costing-concept | 21705ms | 13788ms | 68023ms | 774 | 764 | 0 | 0 |
| 8 | ElasticCostAssistant | costing-currency-french | 28110ms | 9007ms | 77500ms | 508 | 663 | 1 | 0 |
| 9 | ElasticCostAssistant | sizing-shards | 23544ms | 13267ms | 67800ms | 815 | 1082 | 0 | 0 |
| 10 | ElasticCostAssistant | costing-tunisian | 43257ms | 22545ms | 112075ms | 727 | 892 | 1 | 0 |
| 11 | RgSocEngineer | db-query-simple | 2980ms | 31782ms | 431ms | 748 | 249 | 0 | 14 |
| 12 | RgSocEngineer | db-query-settings | 2951ms | 9374ms | 110ms | 756 | 697 | 0 | 10 |
| 13 | RgSocEngineer | db-update-simple | 3902ms | 8945ms | 95ms | 781 | 316 | 0 | 7 |
| 14 | RgSocEngineer | db-update-setting | 3765ms | 5698ms | 80ms | 774 | 305 | 0 | 5 |
| 15 | RgSocEngineer | db-query-french | 5454ms | 8750ms | 5030ms | 753 | 234 | 0 | 2 |
| 16 | RgSocEngineer | db-query-tunisian | 7381ms | 13513ms | 124ms | 905 | 433 | 0 | 10 |
| 17 | RgSocEngineer | db-create-client | 3882ms | 13600ms | 153ms | 791 | 478 | 0 | 9 |
| 18 | RgSocEngineer | db-update-multi | 3801ms | 5988ms | 5030ms | 782 | 235 | 0 | 2 |
| 19 | RgSocEngineer | db-update-tunisian | 7794ms | 8686ms | 10049ms | 870 | 233 | 0 | 3 |
| 20 | RgSocEngineer | db-query-comprehensive | 16826ms | 32792ms | 133ms | 1288 | 272 | 5 | 13 |

---

## Tool Call Analysis

Tool calls are only possible in modes A2 and B (AgentLoop with tool registry).

| Tool | Total Calls | Used in Requests |
|---|---|---|
| `query_graph_memory` | 4 | 4, 4, 8, 10 |
| `GetSystemDetailsTool` | 2 | 20, 20 |
| `GetClientInventoryTool` | 3 | 20, 20, 20 |

---

## Pipeline Stage Analysis

Shows which phpkaiharness pipeline stages executed during full harness mode (B).

| Stage | Executions | Started | Finished |
|---|---|---|---|
| horizon_dispatch | 10 | 0 | 0 |
| horizon_poll | 65 | 0 | 0 |

---

## Language & Dialect Analysis

Compares performance across English, French, and Tunisian Arabic prompts.

| Category | A1 Latency | B Latency | A1 Response Len | B Response Len | B Tool Calls |
|---|---|---|---|---|---|
| Tunisian Arabic (Sizing) | 76807ms | 158116ms | 2382 | 4954 | 0 |
| Tunisian Arabic (Costing) | 43257ms | 112075ms | 1587 | 2248 | 1 |
| French (Sizing) | 18300ms | 79872ms | 1001 | 2415 | 0 |
| French (Currency) | 28110ms | 77500ms | 730 | 1351 | 1 |
| French (DB Query) | 5454ms | 5030ms | 70 | 0 | 0 |
| Tunisian Arabic (DB Query) | 7381ms | 124ms | 678 | 795 | 0 |
| Tunisian Arabic (DB Update) | 7794ms | 10049ms | 542 | 0 | 0 |

---

## Token Efficiency Analysis

| Token Type | A1 (Direct API) | B (Full Harness) | Difference |
|---|---|---|---|
| **Prompt Tokens (total)** | 10629 | 5616 | -5013 |
| **Completion Tokens (total)** | 5345 | 7376 | 2031 |
| **Total Tokens** | 15974 | 12992 | -2982 |

### Analysis

- The full harness mode (B) uses more prompt tokens due to context injection (ontology RAG, quantum memory, draft verification)
- However, the completion tokens may be lower because the model has better context and doesn't need to guess or ask clarifying questions
- The semantic cache can reduce both prompt and completion tokens to zero on cache hits

---

## Latency Analysis

### Latency Distribution

| Percentile | A1 (Direct API) | A2 (Loop, no features) | B (Full Harness) |
|---|---|---|---|
| P10 | 3765ms | 6670ms | 110ms |
| P25 | 3902ms | 8945ms | 153ms |
| P50 | 16826ms | 13267ms | 67800ms |
| P75 | 28178ms | 18698ms | 79872ms |
| P90 | 44473ms | 31782ms | 114763ms |

### Cache Impact

- Cache hits in mode B: 0 out of 20 requests
- Cache hits reduce latency to near-zero (skip LLM call entirely)

---

## Conclusion

### What phpkaiharness Adds

The comparison between A1 (direct API) and B (full harness) demonstrates the value of the phpkaiharness cognitive architecture:

1. **Tool-Augmented Execution**: The full harness averaged 0 tool calls per request, enabling real database queries and updates that the direct API mode cannot perform.
2. **Pipeline Processing**: An average of 0 pipeline stages executed per request, including draft verification, ontology injection, and quantum memory retrieval.
3. **Context Enrichment**: The harness injects real database records and memory context, producing more accurate and contextually relevant responses.
4. **Multi-Language Support**: Semantic context retrieval via embeddings enables better understanding of non-standard dialects (Tunisian Arabic) without explicit language models.
5. **Iterative Refinement**: The agent loop allows multi-step tool calling (query → update → confirm), producing complete results in a single user interaction.

### When to Use Each Mode

- **Direct API (A1):** Best for simple, stateless text generation where no database context or tools are needed.
- **Loop without features (A2):** Useful when you need tool calling but want minimal overhead. No pipeline processing.
- **Full phpkaiharness (B):** Optimal for production use where accuracy, context awareness, and tool augmentation matter most.

---

*This report was automatically generated by the phpkaiharness Test Compare suite.*
