<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreExperimentNodeRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'node_name' => ['required', 'string', 'max:255', Rule::in(array_keys(config('harness.feature_graph.nodes', [])))],
            'enabled' => ['required', 'boolean'],
            'threshold' => ['required', 'numeric', 'between:0,1'],
        ];
    }
}
