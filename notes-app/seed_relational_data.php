<?php

/**
 * Multi-Table Relational Schema Seeder for Quantum Note App
 * Creates: tags, note_tags (pivot), and comments tables
 */
$dbFile = __DIR__.'/database.sqlite';
$pdo = new PDO('sqlite:'.$dbFile);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->exec('PRAGMA journal_mode=WAL;');
$pdo->exec('PRAGMA cache_size=-204800;');
$pdo->exec('PRAGMA mmap_size=2147483648;');

echo "=========================================================\n";
echo "⚛️  SEEDING MULTI-TABLE RELATIONAL QUANTUM SCHEMA\n";
echo "=========================================================\n\n";

// 1. Create `tags` table
echo "[1/4] Creating 'tags' table...\n";
$pdo->exec('
    CREATE TABLE IF NOT EXISTS tags (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name TEXT NOT NULL UNIQUE,
        color TEXT NOT NULL,
        category TEXT NOT NULL
    );
');

// Insert standard tags if empty
$tagCount = $pdo->query('SELECT COUNT(*) FROM tags')->fetchColumn();
if ($tagCount == 0) {
    $tagsData = [
        ['Critical Security', '#ef4444', 'security'],
        ['Compliance Audit', '#f97316', 'security'],
        ['Cost Optimization', '#10b981', 'pricing'],
        ['Budget Approved', '#06b6d4', 'pricing'],
        ['Capacity Sizing', '#8b5cf6', 'sizing'],
        ['Infrastructure', '#ec4899', 'sizing'],
        ['General Note', '#64748b', 'general'],
        ['Quantum Acceleration', '#3cd2a5', 'general'],
    ];

    $stmt = $pdo->prepare('INSERT INTO tags (name, color, category) VALUES (?, ?, ?)');
    foreach ($tagsData as $t) {
        $stmt->execute($t);
    }
    echo '   ✅ Inserted '.count($tagsData)." tags.\n";
} else {
    echo "   ✅ Tags table already populated ({$tagCount} tags).\n";
}

// 2. Create `note_tags` pivot table
echo "[2/4] Creating 'note_tags' pivot table...\n";
$pdo->exec('
    CREATE TABLE IF NOT EXISTS note_tags (
        note_id INTEGER NOT NULL,
        tag_id INTEGER NOT NULL,
        PRIMARY KEY (note_id, tag_id)
    );
');

// Create index for fast JOIN queries
$pdo->exec('CREATE INDEX IF NOT EXISTS idx_note_tags_tag_id ON note_tags(tag_id);');
$pdo->exec('CREATE INDEX IF NOT EXISTS idx_note_tags_note_id ON note_tags(note_id);');

// Populate note_tags pivot table across 1.06M records
$pivotCount = $pdo->query('SELECT COUNT(*) FROM note_tags')->fetchColumn();
if ($pivotCount == 0) {
    echo "   -> Assigning relational tags to 1,060,015 notes...\n";
    $allTagIds = $pdo->query('SELECT id FROM tags')->fetchAll(PDO::FETCH_COLUMN);

    $pdo->beginTransaction();
    $insertPivot = $pdo->prepare('INSERT OR IGNORE INTO note_tags (note_id, tag_id) VALUES (:note_id, :tag_id)');

    $stmtNotes = $pdo->query('SELECT id, category FROM notes');
    $count = 0;
    while ($note = $stmtNotes->fetch(PDO::FETCH_ASSOC)) {
        $noteId = $note['id'];
        $cat = strtolower($note['category'] ?? 'general');

        // Assign tag based on category and note ID pattern
        $tagId1 = match ($cat) {
            'security' => ($noteId % 2 === 0 ? 1 : 2),
            'pricing' => ($noteId % 2 === 0 ? 3 : 4),
            'sizing' => ($noteId % 2 === 0 ? 5 : 6),
            default => ($noteId % 2 === 0 ? 7 : 8),
        };

        $insertPivot->execute([':note_id' => $noteId, ':tag_id' => $tagId1]);

        // Add a second tag for every 3rd note to create rich multi-tag JOIN results
        if ($noteId % 3 === 0) {
            $tagId2 = ($tagId1 % count($allTagIds)) + 1;
            $insertPivot->execute([':note_id' => $noteId, ':tag_id' => $tagId2]);
        }

        $count++;
        if ($count % 50000 === 0) {
            $pdo->commit();
            $pdo->beginTransaction();
            echo "      ... Linked {$count} notes to relational tags\n";
        }
    }
    $pdo->commit();
    echo "   ✅ Successfully populated note_tags pivot table!\n";
} else {
    echo '   ✅ note_tags pivot table already populated ('.number_format($pivotCount)." links).\n";
}

// 3. Create `comments` table for 1-to-many revision/comment JOINs
echo "[3/4] Creating 'comments' table...\n";
$pdo->exec('
    CREATE TABLE IF NOT EXISTS comments (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        note_id INTEGER NOT NULL,
        user_id INTEGER NOT NULL,
        comment_text TEXT NOT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    );
');
$pdo->exec('CREATE INDEX IF NOT EXISTS idx_comments_note_id ON comments(note_id);');

$commentCount = $pdo->query('SELECT COUNT(*) FROM comments')->fetchColumn();
if ($commentCount == 0) {
    echo "   -> Seeding initial comments and revision logs...\n";
    $pdo->beginTransaction();
    $stmtComment = $pdo->prepare('INSERT INTO comments (note_id, user_id, comment_text) VALUES (?, ?, ?)');

    // Seed comments for first 500 notes
    for ($i = 1; $i <= 500; $i++) {
        $userId = ($i % 50) + 1;
        $stmtComment->execute([$i, $userId, "Reviewed cognitive vector score for note #{$i}. Quantum phase alignment confirmed."]);
        if ($i % 2 === 0) {
            $stmtComment->execute([$i, ($userId % 50) + 1, 'Automated compliance check passed. Phase angle matched.']);
        }
    }
    $pdo->commit();
    echo "   ✅ Seeded sample comments.\n";
} else {
    echo '   ✅ Comments table already populated ('.number_format($commentCount)." comments).\n";
}

echo "[4/4] Verifying schema integrity...\n";
$tCount = $pdo->query('SELECT COUNT(*) FROM tags')->fetchColumn();
$ntCount = $pdo->query('SELECT COUNT(*) FROM note_tags')->fetchColumn();
$cCount = $pdo->query('SELECT COUNT(*) FROM comments')->fetchColumn();

echo "\n---------------------------------------------------------\n";
echo "🎉 MULTI-TABLE RELATIONAL SCHEMA SEEDED SUCCESSFULLY!\n";
echo 'Tags Table:       '.number_format($tCount)." rows\n";
echo 'Note_Tags Pivot:  '.number_format($ntCount)." relationships\n";
echo 'Comments Table:   '.number_format($cCount)." rows\n";
echo "---------------------------------------------------------\n";
