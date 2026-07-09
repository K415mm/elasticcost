@extends('layouts.app')

@section('title', 'Explore SQLite Database')

@section('content')
<ul class="breadcrumb">
    <li class="breadcrumb-item"><a href="{{ route('clients.index') }}">Clients</a></li>
    <li class="breadcrumb-item"><a href="{{ route('sqlite.index') }}">SQLite Monitor</a></li>
    <li class="breadcrumb-item active">EXPLORE</li>
</ul>

<div class="d-flex align-items-center mb-4">
    <div class="flex-grow-1">
        <h1 class="page-header mb-0">
            Exploring: {{ $dbName }}
            <small class="d-block mt-1">Execute SQL statements directly against this isolated SQLite database</small>
        </h1>
    </div>
    <div>
        <a href="{{ route('sqlite.index') }}" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left me-1"></i> Back to Monitor
        </a>
    </div>
</div>

<div class="row">
    <!-- Left: Tables list -->
    <div class="col-xl-3 col-lg-4 mb-4">
        <div class="card h-100">
            <div class="card-body">
                <h5 class="card-title text-theme mb-3">
                    <i class="bi bi-table me-2"></i> Database Tables
                </h5>
                <p class="text-muted small mb-3">Click on a table to run a quick select query.</p>
                
                <div class="list-group list-group-flush">
                    @forelse($tables as $table)
                        <button type="button" 
                                onclick="setQuery('SELECT * FROM {{ $table }} LIMIT 50;')" 
                                class="list-group-item list-group-item-action bg-transparent text-white border-secondary border-opacity-35 py-2 px-1">
                            <i class="bi bi-layout-three-columns text-theme me-2 small"></i>{{ $table }}
                        </button>
                    @empty
                        <div class="text-muted small py-3">No tables found.</div>
                    @endforelse
                </div>
            </div>
            <div class="card-arrow">
                <div class="card-arrow-top-left"></div>
                <div class="card-arrow-top-right"></div>
                <div class="card-arrow-bottom-left"></div>
                <div class="card-arrow-bottom-right"></div>
            </div>
        </div>
    </div>

    <!-- Right: Query console and results -->
    <div class="col-xl-9 col-lg-8 mb-4">
        <!-- SQL Editor -->
        <div class="card mb-4">
            <div class="card-body">
                <h5 class="card-title text-theme mb-3">
                    <i class="bi bi-terminal me-2"></i> SQL Query Console
                </h5>
                
                <form action="{{ route('sqlite.explore', ['db' => $dbName]) }}" method="POST">
                    @csrf
                    <div class="mb-3">
                        <textarea name="sql" id="sqlEditor" rows="5" class="form-control mono-cell bg-dark bg-opacity-30 text-white border-secondary border-opacity-40" placeholder="SELECT * FROM table_name;" required>{{ $sql }}</textarea>
                    </div>
                    <button type="submit" class="btn btn-outline-theme py-2 px-4">
                        <i class="bi bi-play-fill me-1"></i> Execute Query
                    </button>
                </form>
            </div>
            <div class="card-arrow">
                <div class="card-arrow-top-left"></div>
                <div class="card-arrow-top-right"></div>
                <div class="card-arrow-bottom-left"></div>
                <div class="card-arrow-bottom-right"></div>
            </div>
        </div>

        <!-- Query Error -->
        @if($error)
            <div class="alert alert-danger mb-4" role="alert">
                <h6 class="alert-heading fw-bold mb-1"><i class="bi bi-exclamation-octagon me-2"></i>Database Error</h6>
                <p class="mb-0 mono-cell small">{{ $error }}</p>
            </div>
        @endif

        <!-- Query Results -->
        @if($queryResult)
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title text-theme mb-3 d-flex align-items-center">
                        <i class="bi bi-card-list me-2"></i> Query Results 
                        <span class="badge bg-secondary bg-opacity-30 text-muted ms-auto fs-6 fw-normal py-1">{{ $queryResult['count'] }} rows returned</span>
                    </h5>
                    
                    @if($queryResult['count'] > 0)
                        <div class="table-responsive" style="max-height: 450px;">
                            <table class="table table-hover align-middle table-sm border-secondary border-opacity-30 mb-0">
                                <thead>
                                    <tr class="bg-secondary bg-opacity-10">
                                        @foreach($queryResult['headers'] as $header)
                                            <th class="border-secondary border-opacity-35 text-white py-2 px-3">{{ $header }}</th>
                                        @endforeach
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($queryResult['rows'] as $row)
                                        <tr>
                                            @foreach($queryResult['headers'] as $header)
                                                <td class="border-secondary border-opacity-25 py-2 px-3 mono-cell" style="max-width: 300px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;" title="{{ is_array($row[$header]) ? json_encode($row[$header]) : $row[$header] }}">
                                                    {{ is_array($row[$header]) ? json_encode($row[$header]) : $row[$header] }}
                                                </td>
                                            @endforeach
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @else
                        <div class="text-center py-4 text-muted">
                            <i class="bi bi-check-circle d-block mb-2 fs-3 text-success"></i>
                            Query completed successfully, but returned 0 rows.
                        </div>
                    @endif
                </div>
                <div class="card-arrow">
                    <div class="card-arrow-top-left"></div>
                    <div class="card-arrow-top-right"></div>
                    <div class="card-arrow-bottom-left"></div>
                    <div class="card-arrow-bottom-right"></div>
                </div>
            </div>
        @endif
    </div>
</div>

<script>
    function setQuery(sql) {
        document.getElementById('sqlEditor').value = sql;
        document.getElementById('sqlEditor').focus();
    }
</script>
@endsection
