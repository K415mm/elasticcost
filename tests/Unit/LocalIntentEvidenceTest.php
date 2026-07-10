<?php

use App\Ai\Routing\LocalIntentEvidenceExtractor;
use App\Ai\Routing\LocalIntentRouter;

test('explicit action with a concrete target is high confidence', function () {
    $evidence = (new LocalIntentEvidenceExtractor)->extract('Update the EDR device count for Acme client');

    expect($evidence->isHighConfidenceAction())->toBeTrue()
        ->and($evidence->requiresCurrentState)->toBeTrue()
        ->and($evidence->confidence)->toBeGreaterThanOrEqual(0.9);
});

test('update as part of a read question does not bypass the router', function () {
    $evidence = (new LocalIntentEvidenceExtractor)->extract('What is the update frequency for the EDR agent?');

    expect($evidence->isHighConfidenceAction())->toBeFalse()
        ->and($evidence->isQuestion)->toBeTrue()
        ->and($evidence->actionVerbs)->toContain('update');
});

test('negated action does not bypass the router', function () {
    $evidence = (new LocalIntentEvidenceExtractor)->extract('Do not update the client status');

    expect($evidence->isHighConfidenceAction())->toBeFalse()
        ->and($evidence->hasNegation)->toBeTrue();
});

test('hypothetical action does not bypass the router', function () {
    $evidence = (new LocalIntentEvidenceExtractor)->extract('How would I update the client status?');

    expect($evidence->isHighConfidenceAction())->toBeFalse()
        ->and($evidence->isHypothetical)->toBeTrue();
});

test('substring matches are not treated as action terms', function () {
    $evidence = (new LocalIntentEvidenceExtractor)->extract('The client has an updater service for status pages');

    expect($evidence->actionVerbs)->toBeEmpty()
        ->and($evidence->isHighConfidenceAction())->toBeFalse();
});

test('read requests with current targets remain eligible for safe router classification', function () {
    $evidence = (new LocalIntentEvidenceExtractor)->extract('Show the current settings for the SIEM agent');

    expect($evidence->requiresCurrentState)->toBeTrue()
        ->and($evidence->isHighConfidenceAction())->toBeFalse()
        ->and($evidence->targets)->toContain('siem');
});

test('prompt evidence is normalized consistently', function () {
    $evidence = (new LocalIntentEvidenceExtractor)->extract('  Update   the client status  ');

    expect($evidence->prompt)->toBe('update the client status');
});

test('local router selects action only for high confidence evidence', function () {
    $decision = (new LocalIntentRouter)->decide('Update the client status');

    expect($decision->isLocalAction())->toBeTrue()
        ->and($decision->method)->toBe('local-intent-action');
});

test('local router falls back when disabled', function () {
    $decision = (new LocalIntentRouter)->decide('Update the client status', enabled: false);

    expect($decision->isLocalAction())->toBeFalse()
        ->and($decision->method)->toBe('router-classification');
});
