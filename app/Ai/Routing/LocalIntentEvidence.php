<?php

namespace App\Ai\Routing;

final readonly class LocalIntentEvidence
{
    /**
     * @param  array<string>  $actionVerbs
     * @param  array<string>  $targets
     * @param  array<string>  $signals
     */
    public function __construct(
        public string $prompt,
        public array $actionVerbs,
        public array $targets,
        public array $signals,
        public bool $hasNegation,
        public bool $isQuestion,
        public bool $isHypothetical,
        public bool $requiresCurrentState,
        public float $confidence,
    ) {}

    public function isHighConfidenceAction(float $threshold = 0.9): bool
    {
        return $this->requiresCurrentState
            && ! $this->hasNegation
            && ! $this->isQuestion
            && ! $this->isHypothetical
            && $this->confidence >= $threshold;
    }

    public function isAmbiguous(float $threshold = 0.9): bool
    {
        return $this->confidence < $threshold;
    }
}
