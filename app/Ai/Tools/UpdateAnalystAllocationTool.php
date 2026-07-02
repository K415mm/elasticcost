<?php

namespace App\Ai\Tools;

use App\Models\ClientScenarioAnalystAllocation;
use App\Models\ClientScenarioMsspDetail;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class UpdateAnalystAllocationTool implements Tool
{
    public function description(): Stringable|string
    {
        return 'Updates the allocation percentage, custom monthly salary, or staff count of a specific SOC role inside a client scenario\'s costing model.';
    }

    public function handle(Request $request): Stringable|string
    {
        $clientId = $request['client_id'] ?? null;
        $scenarioId = $request['scenario_id'] ?? null;
        $socRoleId = $request['soc_role_id'] ?? null;

        if (is_null($clientId) || is_null($scenarioId) || is_null($socRoleId)) {
            return 'Error: client_id, scenario_id, and soc_role_id are required.';
        }

        $detail = ClientScenarioMsspDetail::where('client_id', $clientId)
            ->where('scenario_id', $scenarioId)
            ->first();

        if (! $detail) {
            return "Error: ClientScenarioMsspDetail not found for client_id={$clientId} and scenario_id={$scenarioId}.";
        }

        $allocation = ClientScenarioAnalystAllocation::where('mssp_details_id', $detail->id)
            ->where('soc_role_id', $socRoleId)
            ->first();

        if (! $allocation) {
            $allocation = new ClientScenarioAnalystAllocation([
                'mssp_details_id' => $detail->id,
                'soc_role_id' => $socRoleId,
                'allocation_percentage' => 0.00,
                'staff_count' => 0,
            ]);
        }

        $updates = [];
        if ($request->offsetExists('allocation_percentage')) {
            $updates['allocation_percentage'] = (float) $request['allocation_percentage'];
        }
        if ($request->offsetExists('custom_monthly_salary')) {
            $updates['custom_monthly_salary'] = (float) $request['custom_monthly_salary'];
        }
        if ($request->offsetExists('staff_count')) {
            $updates['staff_count'] = (int) $request['staff_count'];
        }

        if (empty($updates)) {
            return 'No allocation updates (allocation_percentage, custom_monthly_salary, staff_count) were specified in arguments.';
        }

        $allocation->fill($updates)->save();

        return "Successfully updated SOC role ID {$socRoleId} allocation in scenario ID {$scenarioId} for client ID {$clientId}. New values: ".json_encode($updates);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'client_id' => $schema->integer()->description('The ID of the target client.')->required(),
            'scenario_id' => $schema->integer()->description('The ID of the target scenario.')->required(),
            'soc_role_id' => $schema->integer()->description('The ID of the SOC role to update.')->required(),
            'allocation_percentage' => $schema->number()->description('The new allocation percentage (e.g. 10.00 for 10%).'),
            'custom_monthly_salary' => $schema->number()->description('Optional custom monthly salary for this role override.'),
            'staff_count' => $schema->integer()->description('Optional staff count override for this role.'),
        ];
    }
}
