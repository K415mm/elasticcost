<?php

namespace App\Ai\Adapters;

use Illuminate\JsonSchema\JsonSchemaTypeFactory;
use Laravel\Ai\Contracts\Tool as LaravelTool;
use Laravel\Ai\ObjectSchema;
use Laravel\Ai\Tools\Request as LaravelRequest;
use Laravel\Ai\Tools\ToolNameResolver;
use Phpkaiharness\Contracts\ToolInterface;

class LaravelToolAdapter implements ToolInterface
{
    protected LaravelTool $laravelTool;

    public function __construct(LaravelTool $laravelTool)
    {
        $this->laravelTool = $laravelTool;
    }

    /**
     * Get the tool name resolved by the Laravel AI SDK.
     */
    public function name(): string
    {
        return ToolNameResolver::resolve($this->laravelTool);
    }

    /**
     * Get the tool description.
     */
    public function description(): string
    {
        return (string) $this->laravelTool->description();
    }

    /**
     * Build the tool parameter schema in standard JSON-schema format.
     */
    public function schema(): array
    {
        $schema = $this->laravelTool->schema(new JsonSchemaTypeFactory);
        $schemaArray = ! empty($schema)
            ? (new ObjectSchema($schema))->toSchema()
            : [];

        return [
            'type' => 'object',
            'properties' => $schemaArray['properties'] ?? (object) [],
            'required' => $schemaArray['required'] ?? [],
        ];
    }

    /**
     * Execute the Laravel tool using a LaravelRequest object.
     */
    public function execute(array $args): string
    {
        return (string) $this->laravelTool->handle(new LaravelRequest($args));
    }
}
