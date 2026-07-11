<?php

use function Laravel\Folio\{middleware};

middleware(['auth']);

?>

@extends('layouts.app')

@section('title', 'Pipeline Method Matrix - Experiment')

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <h1 class="page-header">Pipeline Method Matrix <span class="badge bg-secondary">Folio</span></h1>
            <p class="text-muted">Live, page-based route served by Laravel Folio. <a href="{{ url('/experiments/precognition') }}">Configure a node</a></p>

            <div class="card">
                <div class="card-header">Feature Graph Nodes</div>
                <div class="card-body">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>Node</th>
                                <th>Status</th>
                                <th>Layer</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach (config('harness.feature_graph.nodes', []) as $node => $config)
                            <tr>
                                <td class="mono-cell">{{ $node }}</td>
                                <td>
                                    @if ($config['enabled'] ?? false)
                                        <span class="badge bg-success">enabled</span>
                                    @else
                                        <span class="badge bg-secondary">disabled</span>
                                    @endif
                                </td>
                                <td>
                                    @if (in_array($node, ['semantic_cache', 'context_compactor', 'guardrails', 'cognitive_memory', 'quantum_harness', 'ontology_injection', 'model_optimizer', 'context_compression', 'draft_verification']))
                                        decision / observer
                                    @else
                                        end-to-end / LLM
                                    @endif
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
