<?php
/**
 * One-off helper to re-run the DnD restaurant migration while keeping its data.
 * Usage (through tinker): $mode = 'export' | 'import'; require 'storage/app/dnd_restaurant_reload.php';
 */

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// In an order where every table comes after the tables its foreign keys point to
$tables = [
    'dnd__restaurant_restaurant',
    'dnd__restaurant_restaurant_user',
    'dnd__restaurant_recipe',
    'dnd__restaurant_ingredient',
    'dnd__restaurant_recipe_ingredient',
    'dnd__restaurant_recipe_byproduct',
    'dnd__restaurant_equipment',
    'dnd__restaurant_recipe_equipment',
    'dnd__restaurant_owned_equipment',
    'dnd__restaurant_storage',
    'dnd__restaurant_storage_ingredient',
    'dnd__restaurant_storage_equipment',
    'dnd__restaurant_room',
    'dnd__restaurant_table',
    'dnd__restaurant_customer',
];

$backup = storage_path('app/dnd_restaurant_backup.json');

if ($mode === 'export') {
    $data = [];
    foreach ($tables as $table) {
        $data[$table] = Schema::hasTable($table) ? DB::table($table)->get()->map(fn ($row) => (array) $row)->all() : [];
    }

    file_put_contents($backup, json_encode($data, JSON_PRETTY_PRINT));
    foreach ($data as $table => $rows) {
        echo $table, ': ', count($rows), ' rows exported', PHP_EOL;
    }
    return;
}

if ($mode === 'import') {
    $data = json_decode(file_get_contents($backup), true);

    DB::transaction(function () use ($tables, $data) {
        foreach ($tables as $table) {
            // Only columns that still exist are restored, new columns fall back to their defaults
            $columns = Schema::getColumnListing($table);
            $rows = array_map(fn ($row) => array_intersect_key($row, array_flip($columns)), $data[$table] ?? []);

            // The equipment self-reference is restored afterwards, as parents may come after their children
            $parents = [];
            if ($table === 'dnd__restaurant_equipment') {
                foreach ($rows as &$row) {
                    $parents[$row['uuid']] = $row['parent_uuid'];
                    $row['parent_uuid'] = null;
                }
                unset($row);
            }

            foreach (array_chunk($rows, 200) as $chunk) {
                DB::table($table)->insert($chunk);
            }

            foreach (array_filter($parents) as $uuid => $parent) {
                DB::table($table)->where('uuid', $uuid)->update(['parent_uuid' => $parent]);
            }

            echo $table, ': ', DB::table($table)->count(), ' / ', count($rows), ' rows restored', PHP_EOL;
        }
    });
}
