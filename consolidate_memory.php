<?php

/**
 * Consolidates cognitive facts and quantum memory from all per-session DBs
 * into the shared main monitor.db and agent_memory.sqlite.
 * This "warms up" the cache so the next B run benefits from existing memory.
 */
$mainPdo = new PDO('sqlite:storage/app/phpkaiharness/monitor.db');
$quantumPdo = new PDO('sqlite:storage/app/phpkaiharness/agent_memory.sqlite');

$sessionDbs = glob('storage/app/phpkaiharness/sessions/*/monitor.db');
$sessionQuantum = glob('storage/app/phpkaiharness/sessions/*/agent_memory.sqlite');

// ── 1. Consolidate harness_facts ────────────────────────────────
$totalFacts = 0;
$insertFact = $mainPdo->prepare(
    'INSERT OR IGNORE INTO harness_facts (session_id, fact, created_at)
     VALUES (:session_id, :fact, :created_at)'
);

foreach ($sessionDbs as $db) {
    $spdo = new PDO('sqlite:'.$db);
    try {
        $stmt = $spdo->query('SELECT * FROM harness_facts');
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as $row) {
            $insertFact->execute([
                ':session_id' => $row['session_id'],
                ':fact' => $row['fact'],
                ':created_at' => $row['created_at'],
            ]);
            $totalFacts++;
        }
    } catch (Exception $e) {
        // Table may not exist
    }
}

// ── 2. Consolidate harness_sessions & harness_details ───────────
$totalSessions = 0;
$totalDetails = 0;

$insertSession = $mainPdo->prepare(
    'INSERT OR IGNORE INTO harness_sessions (id, prompt, response, method, iterations, total_duration_ms, status, created_at, updated_at)
     VALUES (:id, :prompt, :response, :method, :iterations, :total_duration_ms, :status, :created_at, :updated_at)'
);

$insertDetail = $mainPdo->prepare(
    'INSERT OR IGNORE INTO harness_details (session_id, type, name, payload, response, duration_ms, created_at)
     VALUES (:session_id, :type, :name, :payload, :response, :duration_ms, :created_at)'
);

foreach ($sessionDbs as $db) {
    $spdo = new PDO('sqlite:'.$db);
    try {
        $rows = $spdo->query('SELECT * FROM harness_sessions')->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as $row) {
            $insertSession->execute([
                ':id' => $row['id'],
                ':prompt' => $row['prompt'] ?? null,
                ':response' => $row['response'] ?? null,
                ':method' => $row['method'] ?? null,
                ':iterations' => $row['iterations'] ?? 0,
                ':total_duration_ms' => $row['total_duration_ms'] ?? 0,
                ':status' => $row['status'] ?? 'completed',
                ':created_at' => $row['created_at'],
                ':updated_at' => $row['updated_at'] ?? $row['created_at'],
            ]);
            $totalSessions++;
        }
    } catch (Exception $e) {
    }

    try {
        $rows = $spdo->query('SELECT * FROM harness_details')->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as $row) {
            $insertDetail->execute([
                ':session_id' => $row['session_id'],
                ':type' => $row['type'],
                ':name' => $row['name'],
                ':payload' => $row['payload'] ?? null,
                ':response' => $row['response'] ?? null,
                ':duration_ms' => $row['duration_ms'] ?? 0,
                ':created_at' => $row['created_at'],
            ]);
            $totalDetails++;
        }
    } catch (Exception $e) {
    }
}

// ── 3. Consolidate quantum memory nodes & vectors ───────────────
$totalNodes = 0;

$insertNode = $quantumPdo->prepare(
    'INSERT OR IGNORE INTO memory_nodes (id, type, content, phase_angle, created_at)
     VALUES (:id, :type, :content, :phase_angle, :created_at)'
);
$insertVector = $quantumPdo->prepare(
    'INSERT OR IGNORE INTO memory_vectors (node_id, embedding)
     VALUES (:node_id, :embedding)'
);

foreach ($sessionQuantum as $db) {
    $spdo = new PDO('sqlite:'.$db);
    try {
        $nodes = $spdo->query('SELECT * FROM memory_nodes')->fetchAll(PDO::FETCH_ASSOC);
        foreach ($nodes as $node) {
            $insertNode->execute([
                ':id' => $node['id'],
                ':type' => $node['type'],
                ':content' => $node['content'],
                ':phase_angle' => $node['phase_angle'],
                ':created_at' => $node['created_at'],
            ]);
            $totalNodes++;
        }
        $vectors = $spdo->query('SELECT * FROM memory_vectors')->fetchAll(PDO::FETCH_ASSOC);
        foreach ($vectors as $vec) {
            $insertVector->execute([
                ':node_id' => $vec['node_id'],
                ':embedding' => $vec['embedding'],
            ]);
        }
    } catch (Exception $e) {
    }
}

// ── 4. Consolidate quantum entanglement pairs ────────────────────
$insertPair = $quantumPdo->prepare(
    'INSERT OR IGNORE INTO entanglement_pairs (node_a_id, node_b_id, entanglement_force)
     VALUES (:a, :b, :force)'
);
foreach ($sessionQuantum as $db) {
    $spdo = new PDO('sqlite:'.$db);
    try {
        $pairs = $spdo->query('SELECT * FROM entanglement_pairs')->fetchAll(PDO::FETCH_ASSOC);
        foreach ($pairs as $pair) {
            $insertPair->execute([':a' => $pair['node_a_id'], ':b' => $pair['node_b_id'], ':force' => $pair['entanglement_force']]);
        }
    } catch (Exception $e) {
    }
}

// ── Summary ──────────────────────────────────────────────────────
echo "✅ Consolidation complete:\n";
echo "   Facts merged       : {$totalFacts}\n";
echo "   Sessions merged    : {$totalSessions}\n";
echo "   Details merged     : {$totalDetails}\n";
echo "   Quantum nodes      : {$totalNodes}\n\n";

// Verify final state
echo "📊 Main DB state after consolidation:\n";
echo '   harness_sessions : '.$mainPdo->query('SELECT COUNT(*) FROM harness_sessions')->fetchColumn()."\n";
echo '   harness_details  : '.$mainPdo->query('SELECT COUNT(*) FROM harness_details')->fetchColumn()."\n";
echo '   harness_facts    : '.$mainPdo->query('SELECT COUNT(*) FROM harness_facts')->fetchColumn()."\n";
echo '   memory_nodes     : '.$quantumPdo->query('SELECT COUNT(*) FROM memory_nodes')->fetchColumn()."\n";
echo '   memory_vectors   : '.$quantumPdo->query('SELECT COUNT(*) FROM memory_vectors')->fetchColumn()."\n";

// Check semantic cache hits
$cacheRows = $mainPdo->query("SELECT COUNT(*) FROM harness_details WHERE type = 'cache'")->fetchColumn();
echo "   cache entries    : {$cacheRows}\n";
