<?php

namespace App\Ai\Routing;

final class LocalIntentRouter
{
    public function __construct(
        private readonly LocalIntentEvidenceExtractor $extractor = new LocalIntentEvidenceExtractor,
    ) {}

    public function decide(string $prompt, bool $enabled = true, float $threshold = 0.9): RoutingDecision
    {
        $evidence = $this->extractor->extract($prompt);

        if ($enabled && $evidence->isHighConfidenceAction($threshold)) {
            return new RoutingDecision(
                method: 'local-intent-action',
                confidence: $evidence->confidence,
                signals: $evidence->signals,
            );
        }

        return new RoutingDecision(
            method: 'router-classification',
            confidence: $evidence->confidence,
            signals: $evidence->signals,
        );
    }
}
