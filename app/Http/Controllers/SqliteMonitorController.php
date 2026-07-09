<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use PDO;

class SqliteMonitorController extends Controller
{
    /**
     * List all available SQLite databases.
     */
    public function index()
    {
        $baseDir = storage_path('app/phpkaiharness');
        if (! File::isDirectory($baseDir)) {
            File::makeDirectory($baseDir, 0755, true, true);
        }

        $allFiles = File::allFiles($baseDir);
        $databases = [];

        foreach ($allFiles as $file) {
            $ext = strtolower($file->getExtension());
            if ($ext === 'db' || $ext === 'sqlite') {
                $relativePath = str_replace($baseDir.DIRECTORY_SEPARATOR, '', $file->getRealPath());
                $databases[] = [
                    'name' => $relativePath,
                    'path' => $file->getRealPath(),
                    'size' => $this->formatBytes($file->getSize()),
                    'modified' => date('Y-m-d H:i:s', $file->getMTime()),
                ];
            }
        }

        return view('settings.sqlite', compact('databases'));
    }

    /**
     * Explore a specific database, show tables, and run SQL queries.
     */
    public function explore(Request $request)
    {
        $dbName = $request->query('db');
        if (! $dbName) {
            return redirect()->route('sqlite.index');
        }

        // Security: Prevent directory traversal
        $baseDir = storage_path('app/phpkaiharness');
        $realBase = realpath($baseDir);
        $targetPath = realpath($baseDir.DIRECTORY_SEPARATOR.$dbName);

        if (! $targetPath || ! str_starts_with($targetPath, $realBase) || ! File::exists($targetPath)) {
            return redirect()->route('sqlite.index')->with('error', 'Invalid database path.');
        }

        $tables = [];
        $queryResult = null;
        $error = null;
        $sql = $request->input('sql', 'SELECT * FROM sqlite_master WHERE type="table";');

        try {
            $pdo = new PDO('sqlite:'.$targetPath);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

            // Get all tables
            $stmt = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%';");
            $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);

            // If query is submitted
            if ($request->isMethod('post') && $request->has('sql')) {
                $sql = $request->input('sql');

                $stmt = $pdo->query($sql);
                $rows = $stmt->fetchAll();

                $queryResult = [
                    'headers' => ! empty($rows) ? array_keys($rows[0]) : [],
                    'rows' => $rows,
                    'count' => count($rows),
                ];
            }
        } catch (\Throwable $e) {
            $error = $e->getMessage();
        }

        return view('settings.sqlite_explore', compact('dbName', 'tables', 'queryResult', 'error', 'sql'));
    }

    private function formatBytes($bytes, $precision = 2)
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= pow(1024, $pow);

        return round($bytes, $precision).' '.$units[$pow];
    }
}
