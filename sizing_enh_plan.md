# Implementation Plan — Elasticsearch Sizing & Storage Ratio Corrections

This plan details the implementation of storage calculation adjustments and RAM-to-disk ratio corrections in the **ElasticCost** application. It corrects calculations for Scenario 2 and ensures all data tiers scale using the user-specified standards.

## User Review Required

> [!IMPORTANT]
> - **Storage Base Calculations**: Storage calculations for all tiers (Hot, Warm, Cold, Frozen) will now use the replicated daily ingested GB (`$dailyIngestedGb`) as the base daily rate, guaranteeing that replica storage constraints defined in the Hot tier carry through retention sizing correctly.
> - **RAM-to-Disk Ratio Updates**: RAM-to-disk ratios are updated to match standard guidelines:
>   - **Hot Tier**: `RAM = Disk / 30` (1:30 ratio)
>   - **Warm Tier**: `RAM = Disk / 80` (1:80 ratio, previously 1:100)
>   - **Cold Tier**: `RAM = Disk / 100` (1:100 ratio, previously 1:400)
>   - **Frozen Tier**: `RAM = Disk / 160` (1:160 ratio, previously 1:1000)

## Open Questions

None.

## Proposed Changes

### 1. Sizing Engine Calculations

Update storage calculations to use the daily ingested GB for all data tiers, and adjust ratios in node recommendations.

#### [MODIFY] [SizingEngine.php](file:///s:/elasticcost/app/Services/SizingEngine.php)

- In the `calculate` method, calculate storage for all tiers using `$dailyIngestedGb` (which incorporates hot replicas):
  ```php
  $dailyIngestedGb = $totalIndexedDailyGb * (1 + $scenario->hot_replicas);
  $hotStorage = $dailyIngestedGb * $scenario->hot_days;
  $warmStorage = $dailyIngestedGb * $scenario->warm_days;
  $coldStorage = $dailyIngestedGb * $scenario->cold_days;
  $frozenStorage = $dailyIngestedGb * $scenario->frozen_days;
  ```
- In the `recommendNodes` method:
  - For `min` profile:
    - Update cold node RAM calculations to use `/ 100` ratio instead of `/ 400`.
  - For `avg` profile:
    - Update warm node RAM calculations to use `/ 80` ratio instead of `/ 100`.
    - Update cold node RAM calculations to use `/ 100` ratio instead of `/ 400`.
    - Update frozen node RAM calculations to use `/ 160` ratio instead of `/ 1000`.
  - For `max` profile:
    - Update warm node RAM calculations to use `/ 80` ratio instead of `/ 100`.
    - Update cold node RAM calculations to use `/ 100` ratio instead of `/ 400`.
    - Update frozen node RAM calculations to use `/ 160` ratio instead of `/ 1000`.

---

### 2. Sizing & Architectural Reference Guide

#### [MODIFY] [elastic_reference_guide.md](file:///s:/elasticcost/elastic_reference_guide.md)

- Update the storage tier table (lines 11–16) with the corrected ratios:
  - **Warm Tier**: `1:80` (Max storage per 64GB node: 5.1 TB).
  - **Cold Tier**: `1:100` (Max storage per 64GB node: 6.4 TB).
  - **Frozen Tier**: `1:160` (Max storage per 64GB node: 10.2 TB).

---

## Verification Plan

### Automated Tests

- Run `php artisan test --compact` to inspect tests.
- Update assertions in [SizingEngineTest.php](file:///s:/elasticcost/tests/Unit/SizingEngineTest.php) to align with the new correct storage sizes and RAM sizes for all scenarios.
- Verify all unit and feature tests are green.

### Manual Verification

- Run `php check_sizing.php` to verify that for Scenario 2, the cold nodes now recommend `16 GB RAM` and `1600 GB disk` (totaling 3.2 TB across 2 nodes) or similar correct values.
