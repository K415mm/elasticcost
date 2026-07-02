# LLM Clients Layer (Palantir AIP-Inspired) Design — phpkaiharness

This document details the architectural design and specifications for the enhanced LLM Clients layer, drawing directly from the Palantir AIP architecture for secure integration, failover, and governance.

---

## 1. Architectural Concepts (Palantir AIP Alignment)

To bring enterprise-grade secure hosting, resiliency, and access controls to `phpkaiharness`, we introduce four major components at the Client layer:
1. **Dynamic Failover & Resiliency** (`FailoverLlmClient`): Seamless automatic failover across different models or providers.
2. **PII Masking & Security Redaction** (`PiiMaskingLlmClient`): Outbound prompt sanitization and inbound response restoration.
3. **Enterprise Model Catalog** (`ModelCatalog`): Central registry for capability-based and cost-based dynamic model routing.
4. **Rate Limiting & Throttling** (`RateLimitedLlmClient`): Throttling requests to prevent rate limit exceptions (HTTP 429).

These components are designed using the **Decorator Pattern**, allowing developers to mix-and-match capabilities dynamically on top of any standard LLM client without modifying their base implementation.

---

## 2. Low-Level Specifications

### 2.1. Model Catalog (`Phpkaiharness\Llm\ModelCatalog`)
A lookup system tracking metadata for various commercial and open-source models:

```php
namespace Phpkaiharness\Llm;

class ModelCatalog
{
    /**
     * @return array{
     *     provider: string,
     *     max_tokens: int,
     *     supports_tools: bool,
     *     supports_streaming: bool,
     *     cost_per_1k_input: float,
     *     cost_per_1k_output: float
     * }|null
     */
    public function getMetadata(string $model): ?array;
}
```

### 2.2. Failover Decorator (`Phpkaiharness\Llm\FailoverLlmClient`)
Sequentially attempts a list of configured clients if failures (network timeouts, API errors) occur:

```php
namespace Phpkaiharness\Llm;

use Phpkaiharness\Contracts\LlmClientInterface;

class FailoverLlmClient implements LlmClientInterface
{
    /**
     * @param array<LlmClientInterface> $clients Ordered list of client instances.
     */
    public function __construct(protected array $clients) {}
}
```

### 2.3. PII Masking Decorator (`Phpkaiharness\Llm\PiiMaskingLlmClient`)
Redacts sensitive identifiers in outbound prompts, and restores them in the final output:

```php
namespace Phpkaiharness\Llm;

use Phpkaiharness\Contracts\LlmClientInterface;

class PiiMaskingLlmClient implements LlmClientInterface
{
    public function __construct(
        protected LlmClientInterface $innerClient,
        protected array $patterns = [
            'email' => '/[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}/',
            'ip' => '/\b\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}\b/',
            'credit_card' => '/\b(?:\d[ -]*?){13,16}\b/'
        ]
    ) {}
}
```

### 2.4. Rate Limiting Decorator (`Phpkaiharness\Llm\RateLimitedLlmClient`)
Enforces request cooling periods or sliding windows to comply with provider rate limits:

```php
namespace Phpkaiharness\Llm;

use Phpkaiharness\Contracts\LlmClientInterface;

class RateLimitedLlmClient implements LlmClientInterface
{
    public function __construct(
        protected LlmClientInterface $innerClient,
        protected int $requestsPerMinute = 60,
        protected int $cooldownMs = 1000
    ) {}
}
```

---

## 3. Decorator Composition Pattern

The decorators can be nested transparently to provide comprehensive enterprise protection:

```
                  ┌─────────────────────────────────────────┐
                  │          PiiMaskingLlmClient            │
                  └────────────────────┬────────────────────┘
                                       │ (wraps)
                  ┌────────────────────▼────────────────────┐
                  │         RateLimitedLlmClient            │
                  └────────────────────┬────────────────────┘
                                       │ (wraps)
                  ┌────────────────────▼────────────────────┐
                  │          FailoverLlmClient              │
                  └──────────┬───────────────────┬──────────┘
                             │ (primary)         │ (fallback)
                  ┌──────────▼─────────┐    ┌────▼──────────┐
                  │   LmStudioClient   │    │ OllamaClient  │
                  └────────────────────┘    └───────────────┘
```

---

## 4. Verification & Testing

- Create tests for `FailoverLlmClient` asserting that if client 1 throws an exception, client 2 is called and its response returned.
- Create tests for `PiiMaskingLlmClient` verifying that a prompt containing an email gets masked before hitting the inner client, and is unmasked in the output response.
- Create tests for `RateLimitedLlmClient` and `ModelCatalog` asserting correctness.
