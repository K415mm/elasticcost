<?php

use App\Models\Scenario;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Collection;

require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

// Force clear the cache
Cache::forget('dashboard:scenarios');
Cache::forget('dashboard:client_summaries');
echo "Cache forgotten\n";

// Store in cache and retrieve
$original = Scenario::all();
echo 'Original type: '.get_class($original)."\n";
echo 'Original first item type: '.get_class($original->first())."\n";

Cache::put('test:scenarios', $original, now()->addMinutes(10));
$retrieved = Cache::get('test:scenarios');
echo 'Retrieved type: '.gettype($retrieved)."\n";
if (is_object($retrieved)) {
    echo 'Retrieved class: '.get_class($retrieved)."\n";
    if ($retrieved instanceof Collection) {
        foreach ($retrieved as $i => $item) {
            echo "Item $i: type=".gettype($item).' class='.(is_object($item) ? get_class($item) : 'N/A');
            if (is_object($item)) {
                echo ' name='.$item->name;
            } elseif (is_string($item)) {
                echo ' value='.$item;
            } elseif (is_array($item)) {
                echo ' keys='.implode(',', array_keys($item));
            }
            echo "\n";
        }
    }
} else {
    echo "Not an object!\n";
    var_dump($retrieved);
}

// Also check what Cache::remember returns
Cache::forget('test:scenarios');
$remembered = Cache::remember('test:scenarios', now()->addMinutes(10), fn () => Scenario::all());
echo "\nRemembered type: ".gettype($remembered)."\n";
if (is_object($remembered) && $remembered instanceof Collection) {
    foreach ($remembered as $i => $item) {
        echo "Remembered Item $i: type=".gettype($item).' class='.(is_object($item) ? get_class($item) : 'N/A');
        if (is_object($item)) {
            echo ' name='.$item->name;
        } elseif (is_string($item)) {
            echo ' value='.$item;
        } elseif (is_array($item)) {
            echo ' keys='.implode(',', array_keys($item));
        }
        echo "\n";
    }
}

Cache::forget('test:scenarios');
