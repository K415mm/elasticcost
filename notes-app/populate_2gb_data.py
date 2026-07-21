import sqlite3
import os
import sys
import time

DB_PATH = os.path.join(os.path.dirname(__file__), "database.sqlite")
TARGET_SIZE_MB = 2050  # > 2.0 GB

def populate_2gb_database():
    print("=================================================================")
    print("     BULK 2.0+ GB DATA GENERATOR FOR NOTES APPLICATION           ")
    print("=================================================================\n")

    print(f"--> Target Database: {DB_PATH}")
    print(f"--> Target File Size: > {TARGET_SIZE_MB} MB (2.0 GB+)")

    conn = sqlite3.connect(DB_PATH)
    conn.execute("PRAGMA journal_mode=WAL;")
    conn.execute("PRAGMA synchronous=NORMAL;")

    conn.execute("""
        CREATE TABLE IF NOT EXISTS users (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL,
            email TEXT UNIQUE NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        );
    """)

    conn.execute("""
        CREATE TABLE IF NOT EXISTS notes (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER NOT NULL,
            title TEXT NOT NULL,
            content TEXT NOT NULL,
            category TEXT DEFAULT 'general',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE
        );
    """)
    conn.commit()

    # Ensure users 1..10 exist
    for u in range(1, 11):
        conn.execute("INSERT OR IGNORE INTO users (id, name, email) VALUES (?, ?, ?)",
                     (u, f"User {u}", f"user{u}@enterprise.io"))
    conn.commit()

    # Check current size
    cur_size_mb = os.path.getsize(DB_PATH) / (1024 * 1024) if os.path.exists(DB_PATH) else 0
    print(f"--> Current Database Size: {cur_size_mb:.2f} MB")

    if cur_size_mb >= TARGET_SIZE_MB:
        print("[SKIP] Database is already over 2.0 GB!")
    else:
        cursor = conn.cursor()
        cursor.execute("SELECT COUNT(*) FROM notes;")
        start_count = cursor.fetchone()[0]

        start_time = time.time()
        batch_size = 5000
        padding_payload = " " + ("X" * 1500) # 1.5 KB payload padding per note to reach 2GB rapidly
        categories = ["security", "pricing", "sizing", "general"]

        print(f"--> Starting bulk generation from note #{start_count + 1}...")

        batch = []
        n = start_count

        while True:
            n += 1
            cat = categories[n % 4]
            user_id = (n % 10) + 1
            title = f"Enterprise Cognitive Log #{n}"
            content = f"Cognitive payload entry #{n} for domain context payload data.{padding_payload}"

            batch.append((user_id, title, content, cat))

            if len(batch) >= batch_size:
                conn.executemany("INSERT INTO notes (user_id, title, content, category) VALUES (?, ?, ?, ?)", batch)
                conn.commit()
                batch = []

                size_mb = os.path.getsize(DB_PATH) / (1024 * 1024)
                sys.stdout.write(f"\r    [PROGRESS] Inserted {n:,} notes | DB Size: {size_mb:.2f} MB / {TARGET_SIZE_MB} MB")
                sys.stdout.flush()

                if size_mb >= TARGET_SIZE_MB:
                    break

        if batch:
            conn.executemany("INSERT INTO notes (user_id, title, content, category) VALUES (?, ?, ?, ?)", batch)
            conn.commit()

        elapsed = time.time() - start_time
        final_mb = os.path.getsize(DB_PATH) / (1024 * 1024)
        print(f"\n    [DONE] Reached {final_mb:.2f} MB in {elapsed:.2f} seconds!")

    # Now insert the specific target needle notes requested by the user
    print("\n--> Inserting specific needle-in-a-haystack target notes...")

    # Keyword 1: "serach for kais 1"
    conn.execute("""
        INSERT INTO notes (user_id, title, content, category)
        VALUES (1, 'Special Needle Note #1', 'Important cognitive security item payload: serach for kais 1 inside database', 'security')
    """)
    id_kais1 = conn.execute("SELECT last_insert_rowid()").fetchone()[0]

    # Keyword 2: "serch for kais2"
    conn.execute("""
        INSERT INTO notes (user_id, title, content, category)
        VALUES (2, 'Special Needle Note #2', 'Important cognitive pricing item payload: serch for kais2 inside database', 'pricing')
    """)
    id_kais2 = conn.execute("SELECT last_insert_rowid()").fetchone()[0]

    conn.commit()

    final_count = conn.execute("SELECT COUNT(*) FROM notes").fetchone()[0]
    final_size = os.path.getsize(DB_PATH) / (1024 * 1024)

    conn.close()

    print("\n=================================================================")
    print("              2.0+ GB DATA GENERATION COMPLETE                   ")
    print("=================================================================")
    print(f"Final File Path:     {DB_PATH}")
    print(f"Final File Size:     {final_size:.2f} MB (Over 2.0 GB!)")
    print(f"Total Notes Stored:  {final_count:,} notes")
    print(f"Needle Note 1 ID:    {id_kais1} (Content contains: 'serach for kais 1')")
    print(f"Needle Note 2 ID:    {id_kais2} (Content contains: 'serch for kais2')")
    print("=================================================================\n")

if __name__ == "__main__":
    populate_2gb_database()
