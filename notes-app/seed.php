<?php

$dbFile = __DIR__.'/database.sqlite';
$pdo = new PDO('sqlite:'.$dbFile);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// Execute table creations
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

echo "Seeding basic SQLite database with sample users and notes...\n";

// Insert Users
$pdo->exec("INSERT OR IGNORE INTO users (id, name, email) VALUES 
(1, 'Alice Developer', 'alice@company.com'),
(2, 'Bob Security', 'bob@company.com'),
(3, 'Charlie Admin', 'charlie@company.com');
");

// Insert Notes
$pdo->exec("INSERT OR IGNORE INTO notes (id, user_id, title, content, category) VALUES 
(1, 1, 'SIEM Cloud Storage Price Benchmark', 'SIEM log ingestion is estimated at $200 per TB monthly on cloud hosting.', 'pricing'),
(2, 2, 'SSL Gateway Certificate Expiry', 'The SSL gateway certificate for auth.company.com expires on August 30.', 'security'),
(3, 3, 'PostgreSQL Cluster Sizing', 'High-availability PostgreSQL database node sizing: 16 vCPU, 64GB RAM, 1TB NVMe SSD.', 'sizing'),
(4, 1, 'Team Sprint Kickoff Notes', 'Quarterly goal: complete quantum memory engine integration and API documentation.', 'general'),
(5, 2, 'OAuth2 Token Security Rotation', 'Rotate secret credentials every 90 days across API gateway instances.', 'security');
");

echo '[SUCCESS] Basic SQLite Notes Database seeded at '.$dbFile."\n";
