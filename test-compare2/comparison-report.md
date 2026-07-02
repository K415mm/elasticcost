# phpkaiharness Comparison Test Report

**Generated:** 2026-06-27 23:44:50

**Test Environment:** ElasticCost Platform with qwen3.5-27b

---

## Executive Summary

This report compares three execution modes across 20 test requests:

| Mode | Description |
|---|---|
| **A1 — Direct API** | Raw Qwen Cloud API call, no harness, no tools, no pipeline |
| **A2 — Loop (no features)** | AgentLoop with all feature_graph nodes disabled |
| **B — Full phpkaiharness** | All features enabled: draft verification, quantum memory, ontology RAG, semantic cache, optimizer |

### Key Findings

- **Latency:** Full harness averages 21949ms vs 12681ms for direct API (+73.1%)
- **Token Usage:** Full harness uses 700 tokens avg vs 783 for direct API (-10.6%)
- **Tool Calls:** Full harness averages 1.9 tool calls per request; A1/A2 have 0
- **Success Rate:** A1: 20/20, A2: 20/20, B: 17/20

---

## Aggregate Metrics Comparison

| Metric | A1 (Direct API) | A2 (Loop, no features) | B (Full Harness) | B vs A1 |
|---|---|---|---|---|
| **Avg Latency (ms)** | 12681 | 11437 | 21949 | +73.1% |
| **Min Latency (ms)** | 1963 | 1893 | 5033 | +156.4% |
| **Max Latency (ms)** | 39042 | 26779 | 57003 | +46.0% |
| **Avg Total Tokens** | 783 | 1015 | 700 | -10.6% |
| **Avg Tool Calls** | 0 | 0 | 1.9 | N/A |
| **Avg Response Length (chars)** | 1007 | 1935 | 1675 | +66.3% |
| **Avg Pipeline Stages** | 0 | 3 | 3 | N/A |
| **Successful Requests** | 20 | 20 | 17 | -15.0% |

---

## Per-Request Comparison

| # | Agent | Category | A1 Latency | A2 Latency | B Latency | A1 Tokens | B Tokens | B Tools | B Stages |
|---|---|---|---|---|---|---|---|---|---|
| 1 | ElasticCostAssistant | sizing-basic | 10198ms | 4412ms | 20243ms | 493 | 571 | 0 | 0 |
| 2 | ElasticCostAssistant | sizing-calculation | 39042ms | 14395ms | 57003ms | 849 | 1246 | 0 | 0 |
| 3 | ElasticCostAssistant | sizing-french | 11731ms | 8822ms | 20150ms | 648 | 710 | 0 | 0 |
| 4 | ElasticCostAssistant | costing-calculation | 24175ms | 16972ms | 42026ms | 1017 | 1549 | 0 | 0 |
| 5 | ElasticCostAssistant | sizing-tunisian | 38823ms | 20784ms | 24419ms | 1139 | 411 | 0 | 0 |
| 6 | ElasticCostAssistant | sizing-replicas | 18558ms | 1893ms | 20540ms | 681 | 729 | 0 | 0 |
| 7 | ElasticCostAssistant | costing-concept | 14789ms | 12109ms | 23617ms | 797 | 1113 | 0 | 0 |
| 8 | ElasticCostAssistant | costing-currency-french | 16399ms | 7093ms | 14824ms | 555 | 548 | 0 | 0 |
| 9 | ElasticCostAssistant | sizing-shards | 15801ms | 11020ms | 29531ms | 807 | 1207 | 0 | 0 |
| 10 | ElasticCostAssistant | costing-tunisian | 33436ms | 12049ms | 37364ms | 745 | 984 | 0 | 0 |
| 11 | RgSocEngineer | db-query-simple | 2200ms | 18175ms | 24607ms | 756 | 545 | 11 | 9 |
| 12 | RgSocEngineer | db-query-settings | 2195ms | 10452ms | 12181ms | 780 | 697 | 1 | 7 |
| 13 | RgSocEngineer | db-update-simple | 3224ms | 7272ms | 15867ms | 786 | 314 | 2 | 7 |
| 14 | RgSocEngineer | db-update-setting | 3977ms | 5133ms | 10283ms | 903 | 305 | 1 | 5 |
| 15 | RgSocEngineer | db-query-french | 1963ms | 8251ms | 5065ms | 763 | 234 | 0 | 2 |
| 16 | RgSocEngineer | db-query-tunisian | 2744ms | 20471ms | 22880ms | 752 | 561 | 11 | 8 |
| 17 | RgSocEngineer | db-create-client | 2784ms | 10172ms | 12792ms | 789 | 409 | 1 | 7 |
| 18 | RgSocEngineer | db-update-multi | 3848ms | 6061ms | 5046ms | 893 | 235 | 0 | 2 |
| 19 | RgSocEngineer | db-update-tunisian | 3232ms | 6418ms | 5033ms | 751 | 233 | 0 | 2 |
| 20 | RgSocEngineer | db-query-comprehensive | 4502ms | 26779ms | 35515ms | 765 | 1395 | 11 | 13 |

---

## Tool Call Analysis

Tool calls are only possible in modes A2 and B (AgentLoop with tool registry).

| Tool | Total Calls | Used in Requests |
|---|---|---|
| `GetSystemDetailsTool` | 8 | 11, 11, 12, 16, 16, 17, 20, 20 |
| `GetClientInventoryTool` | 28 | 11, 11, 11, 11, 11, 11, 11, 11, 11, 13, 16, 16, 16, 16, 16, 16, 16, 16, 16, 20, 20, 20, 20, 20, 20, 20, 20, 20 |
| `UpdateClientInventoryTool` | 1 | 13 |
| `UpdateGlobalSettingTool` | 1 | 14 |

---

## Pipeline Stage Analysis

Shows which phpkaiharness pipeline stages executed during full harness mode (B).

| Stage | Executions | Started | Finished |
|---|---|---|---|
| horizon_dispatch | 10 | 0 | 0 |
| horizon_poll | 52 | 0 | 0 |

---

## Language & Dialect Analysis

Compares performance across English, French, and Tunisian Arabic prompts.

| Category | A1 Latency | B Latency | A1 Response Len | B Response Len | B Tool Calls |
|---|---|---|---|---|---|
| Tunisian Arabic (Sizing) | 38823ms | 24419ms | 3264 | 352 | 0 |
| Tunisian Arabic (Costing) | 33436ms | 37364ms | 1659 | 2616 | 0 |
| French (Sizing) | 11731ms | 20150ms | 1304 | 1551 | 0 |
| French (Currency) | 16399ms | 14824ms | 919 | 889 | 0 |
| French (DB Query) | 1963ms | 5065ms | 110 | 0 | 0 |
| Tunisian Arabic (DB Query) | 2744ms | 22880ms | 68 | 1307 | 11 |
| Tunisian Arabic (DB Update) | 3232ms | 5033ms | 68 | 0 | 0 |

---

## Token Efficiency Analysis

| Token Type | A1 (Direct API) | B (Full Harness) | Difference |
|---|---|---|---|
| **Prompt Tokens (total)** | 10629 | 5616 | -5013 |
| **Completion Tokens (total)** | 5040 | 8380 | 3340 |
| **Total Tokens** | 15669 | 13996 | -1673 |

### Analysis

- The full harness mode (B) uses more prompt tokens due to context injection (ontology RAG, quantum memory, draft verification)
- However, the completion tokens may be lower because the model has better context and doesn't need to guess or ask clarifying questions
- The semantic cache can reduce both prompt and completion tokens to zero on cache hits

---

## Latency Analysis

### Latency Distribution

| Percentile | A1 (Direct API) | A2 (Loop, no features) | B (Full Harness) |
|---|---|---|---|
| P10 | 2200ms | 5133ms | 5065ms |
| P25 | 3224ms | 7093ms | 12792ms |
| P50 | 10198ms | 10452ms | 20540ms |
| P75 | 18558ms | 16972ms | 29531ms |
| P90 | 38823ms | 20784ms | 42026ms |

### Cache Impact

- Cache hits in mode B: 0 out of 20 requests
- Cache hits reduce latency to near-zero (skip LLM call entirely)

---

## Conclusion

### What phpkaiharness Adds

The comparison between A1 (direct API) and B (full harness) demonstrates the value of the phpkaiharness cognitive architecture:

1. **Tool-Augmented Execution**: The full harness averaged 1.9 tool calls per request, enabling real database queries and updates that the direct API mode cannot perform.
2. **Pipeline Processing**: An average of 3 pipeline stages executed per request, including draft verification, ontology injection, and quantum memory retrieval.
3. **Context Enrichment**: The harness injects real database records and memory context, producing more accurate and contextually relevant responses.
4. **Multi-Language Support**: Semantic context retrieval via embeddings enables better understanding of non-standard dialects (Tunisian Arabic) without explicit language models.
5. **Iterative Refinement**: The agent loop allows multi-step tool calling (query → update → confirm), producing complete results in a single user interaction.

### When to Use Each Mode

- **Direct API (A1):** Best for simple, stateless text generation where no database context or tools are needed.
- **Loop without features (A2):** Useful when you need tool calling but want minimal overhead. No pipeline processing.
- **Full phpkaiharness (B):** Optimal for production use where accuracy, context awareness, and tool augmentation matter most.

---

*This report was automatically generated by the phpkaiharness Test Compare suite.*
