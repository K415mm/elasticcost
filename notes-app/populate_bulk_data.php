<?php

/**
 * Bulk Data Generator for Notes CRUD Application (s:\elasticcost\notes-app\database.sqlite)
 */
$dbFile = __DIR__.'/database.sqlite';
echo "=================================================================\n";
echo "       BULK DATA GENERATOR FOR NOTES APPLICATION                 \n";
echo "=================================================================\n\n";

$pdo = new PDO('sqlite:'.$dbFile);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->exec('PRAGMA journal_mode=WAL;');
$pdo->exec('PRAGMA synchronous=NORMAL;');

// Initialize tables if not existing
$pdo->exec("
    CREATE TABLE IF NOT EXISTS users (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name TEXT NOT NULL,
        email TEXT UNIQUE NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    );

    CREATE TABLE IF NOT EXISTS notes (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER NOT NULL,
        title TEXT NOT NULL,
        content TEXT NOT NULL,
        category TEXT DEFAULT 'general',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE
    );
");

// 1. Generate 50 Realistic Users
$firstNames = ['Alex', 'Jordan', 'Taylor', 'Morgan', 'Sam', 'Chris', 'Pat', 'Riley', 'Casey', 'Avery', 'Dakota', 'Reese', 'Skyler', 'Quinn', 'Rowan'];
$lastNames = ['Smith', 'Johnson', 'Williams', 'Brown', 'Jones', 'Garcia', 'Miller', 'Davis', 'Rodriguez', 'Martinez', 'Hernandez', 'Lopez', 'Gonzalez', 'Wilson', 'Anderson'];
$domains = ['security', 'pricing', 'sizing', 'devops', 'cloud', 'sysadmin', 'architecture'];

echo "--> Generating 50 users...\n";

$pdo->beginTransaction();
$userStmt = $pdo->prepare('INSERT OR IGNORE INTO users (name, email) VALUES (:name, :email)');

$userIds = [];
for ($u = 1; $u <= 50; $u++) {
    $fn = $firstNames[array_rand($firstNames)];
    $ln = $lastNames[array_rand($lastNames)];
    $name = "$fn $ln";
    $email = strtolower($fn.'.'.$ln.$u.'@enterprise.io');

    $userStmt->execute([':name' => $name, ':email' => $email]);
    $userIds[] = $u;
}
$pdo->commit();

echo "    [DONE] Users generated.\n\n";

// 2. Generate 10,000 Notes across categories
$noteCount = 10000;
echo "--> Generating {$noteCount} notes across categories (security, pricing, sizing, general)...\n";

$categories = ['security', 'pricing', 'sizing', 'general'];

$topics = [
    'security' => [
        'SSL Gateway Certificate Expiry' => 'SSL certificate for domain auth.gateway.io expires in 30 days. Rotate private keys.',
        'OAuth2 Access Token Scope' => 'Enforce fine-grained JWT scopes across API endpoints to prevent token escalation.',
        'WAF Rate Limiting Policy' => 'Configure Cloudflare WAF rate limiting to 100 requests per minute per IP.',
        'SIEM Log Audit Trail' => 'Export audit logs directly to S3 bucket with KMS encryption enabled.',
        'Vulnerability Patch CVE-2026-1142' => 'Patch Linux kernel buffer overflow vulnerability across production k8s nodes.',
    ],
    'pricing' => [
        'SIEM Storage Monthly Cost' => 'SIEM log storage cost is estimated at $200 per TB per month on cloud storage.',
        'EC2 Reserved Instance Discount' => 'Purchasing 3-year compute savings plan reduces hourly instance cost by 45%.',
        'Database IOPS Storage Tier' => 'Provisioned IOPS SSD (gp3) cost benchmark is $0.08 per GB-month.',
        'Kubernetes Cluster Ingress Bandwidth' => 'Cross-region data transfer pricing is $0.02 per GB transferred.',
        'Cloudflare Enterprise Contract' => 'Enterprise DDoS protection contract baseline is $3,500 monthly.',
    ],
    'sizing' => [
        'PostgreSQL Cluster Master Node' => 'Master node specs: 16 vCPU, 64GB RAM, 1TB NVMe SSD with 10,000 IOPS.',
        'Redis Cache Cluster Sizing' => 'Redis memory cache capacity: 32GB RAM allocated across 3 sharded master nodes.',
        'Kubernetes Worker Node Sizing' => 'Standard worker pool: c6i.2xlarge (8 vCPU, 16GB RAM) running 40 pods per node.',
        'Kafka Streaming Cluster Capacity' => 'Kafka cluster brokers: 5 nodes with 128GB RAM and 4TB SSD per broker.',
        'Elasticsearch Index Shard Count' => 'Primary shards configured to 5 with 2 replica shards per index.',
    ],
    'general' => [
        'Sprint Review & Backlog Scrub' => 'Sprint 42 goal: finalize quantum memory engine integration and deployment tests.',
        'Team Knowledge Base Documentation' => 'Document all C extension build flags and CMake cross-compilation workflows.',
        'Weekly Standup Action Items' => 'Complete performance benchmark comparisons between standard SQLite and custom engine.',
        'Q3 Architecture Roadmap' => 'Transition key microservices to event-driven pub/sub messaging architecture.',
        'Developer Onboarding Checklist' => 'Ensure all new team members receive SSH keys, staging credentials, and repo access.',
    ],
];

$pdo->beginTransaction();
$noteStmt = $pdo->prepare('INSERT INTO notes (user_id, title, content, category) VALUES (:user_id, :title, :content, :category)');

for ($n = 1; $n <= $noteCount; $n++) {
    $userId = $userIds[array_rand($userIds)];
    $cat = $categories[array_rand($categories)];
    $catTopics = $topics[$cat];
    $titleKey = array_rand($catTopics);
    $content = $catTopics[$titleKey].' (Item sample #'.$n.' for load verification)';
    $title = $titleKey.' #'.$n;

    $noteStmt->execute([
        ':user_id' => $userId,
        ':title' => $title,
        ':content' => $content,
        ':category' => $cat,
    ]);

    if ($n % 2000 == 0) {
        echo "    Progress: {$n} / {$noteCount} notes inserted...\n";
    }
}
$pdo->commit();

// Database Stats
$userTotal = $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
$noteTotal = $pdo->query('SELECT COUNT(*) FROM notes')->fetchColumn();
$sizeMb = round(filesize($dbFile) / (1024 * 1024), 2);

echo "\n=================================================================\n";
echo "                 BULK DATA POPULATION COMPLETE                   \n";
echo "=================================================================\n";
echo sprintf("Database File Path: %s\n", $dbFile);
echo sprintf("Database File Size: %.2f MB\n", $sizeMb);
echo sprintf("Total Users:        %d users\n", $userTotal);
echo sprintf("Total Notes:        %d notes\n", $noteTotal);
echo "=================================================================\n";
