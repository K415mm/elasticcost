<?php

use function Laravel\Folio\{middleware};

middleware(['auth']);

?>

@extends('layouts.app')

@section('title', 'Precognition Node Configuration - Experiment')

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-md-6">
            <h1 class="page-header">Node Configurator <span class="badge bg-info">Precognition</span></h1>
            <p class="text-muted">Type to validate against the backend form request in real time.</p>

            <div class="card">
                <div class="card-body">
                    <form id="precognition-form" method="POST" action="{{ route('experiments.precognition.store') }}">
                        @csrf
                        <div class="mb-3">
                            <label for="node_name" class="form-label">Node name</label>
                            <select class="form-select" id="node_name" name="node_name">
                                <option value="">Select a node</option>
                                @foreach (array_keys(config('harness.feature_graph.nodes', [])) as $node)
                                <option value="{{ $node }}">{{ $node }}</option>
                                @endforeach
                            </select>
                            <div class="invalid-feedback" id="error-node_name"></div>
                        </div>

                        <div class="mb-3">
                            <label for="threshold" class="form-label">Threshold (0-1)</label>
                            <input type="number" step="0.01" min="0" max="1" class="form-control" id="threshold" name="threshold" value="0.90">
                            <div class="invalid-feedback" id="error-threshold"></div>
                        </div>

                        <div class="mb-3 form-check">
                            <input type="checkbox" class="form-check-input" id="enabled" name="enabled" value="1">
                            <label class="form-check-label" for="enabled">Enabled</label>
                            <div class="invalid-feedback" id="error-enabled"></div>
                        </div>

                        <button type="submit" class="btn btn-primary">Validate configuration</button>
                        <div id="success-message" class="alert alert-success mt-3 d-none"></div>
                    </form>
                </div>
            </div>

            <p class="mt-3"><a href="{{ url('/experiments/pipeline') }}">Back to matrix</a></p>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
(function () {
    const form = document.getElementById('precognition-form');
    const successMessage = document.getElementById('success-message');

    function setError(field, message) {
        const el = document.getElementById('error-' + field);
        const input = form.elements[field];
        if (el && input) {
            el.textContent = message;
            input.classList.add('is-invalid');
            el.style.display = 'block';
        }
    }

    function clearError(field) {
        const el = document.getElementById('error-' + field);
        const input = form.elements[field];
        if (el && input) {
            el.textContent = '';
            input.classList.remove('is-invalid');
            el.style.display = 'none';
        }
    }

    function validateField(input) {
        const field = input.name;
        clearError(field);

        const formData = new FormData(form);
        formData.set('enabled', form.elements.enabled.checked ? '1' : '0');

        fetch(form.action, {
            method: 'POST',
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'Precognition': 'true',
                'Precognition-Validate-Only': field,
            },
            body: formData,
            credentials: 'same-origin',
        })
        .then(response => {
            if (response.status === 204) {
                return { ok: true };
            }
            return response.json().then(data => ({ ok: false, data }));
        })
        .then(result => {
            if (!result.ok) {
                const messages = result.data.errors && result.data.errors[field]
                    ? result.data.errors[field].join(' ')
                    : result.data.message || 'Invalid';
                setError(field, messages);
            }
        })
        .catch(error => {
            console.error('Precognition validation error', error);
        });
    }

    form.querySelectorAll('input, select').forEach(input => {
        input.addEventListener('change', () => validateField(input));
    });

    form.addEventListener('submit', function (event) {
        event.preventDefault();
        form.querySelectorAll('input, select').forEach(input => clearError(input.name));
        successMessage.classList.add('d-none');
        successMessage.textContent = '';

        const formData = new FormData(form);
        formData.set('enabled', form.elements.enabled.checked ? '1' : '0');

        fetch(form.action, {
            method: 'POST',
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: formData,
            credentials: 'same-origin',
        })
        .then(response => {
            if (response.ok) {
                return response.json().then(data => ({ ok: true, data }));
            }
            return response.json().then(data => ({ ok: false, data }));
        })
        .then(result => {
            if (result.ok) {
                successMessage.textContent = result.data.message || 'Configuration valid.';
                successMessage.classList.remove('d-none');
            } else {
                Object.keys(result.data.errors || {}).forEach(field => {
                    setError(field, result.data.errors[field].join(' '));
                });
            }
        })
        .catch(error => {
            console.error('Precognition submit error', error);
        });
    });
})();
</script>
@endsection
