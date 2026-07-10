<?php

namespace App\Ai\Routing;

final readonly class RoutingDecision
{
    /**
     * @param  array<string>  $signals
     */
    public function __construct(
        public string $method,
        public float $confidence,
        public array $signals,
    ) {}

    public function isLocalAction(): bool
    {
        return $this->method === 'local-intent-action';
    }
}
