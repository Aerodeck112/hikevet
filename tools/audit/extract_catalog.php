<?php
// Extrage literalul treatment_catalog() din Wallet Core in JSON (date pure, fara executia pluginului).
$src = file_get_contents(__DIR__ . '/../../source-wordpress/hikevet-client-wallet-core/hikevet-client-wallet-core.php');
if (!preg_match('/private function treatment_catalog\(\) \{\s*return (array\(.*?\n\));\s*\}/s', $src, $m)) { fwrite(STDERR, "catalog negasit\n"); exit(1); }
$literal = $m[1];
// Garda: literalul trebuie sa contina doar array(), siruri, numere si =>.
$stripped = preg_replace('/"(?:[^"\\\\]|\\\\.)*"|\'(?:[^\'\\\\]|\\\\.)*\'/', '', $literal);
if (preg_match('/[a-zA-Z_]+\s*\(/', str_replace('array(', '', $stripped)) || preg_match('/[\$;`]/', $stripped)) { fwrite(STDERR, "literal nesigur\n"); exit(1); }
$catalog = eval('return ' . $literal . ';');
file_put_contents(__DIR__ . '/../../database/seeders/data/treatment-catalog.json', json_encode($catalog, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
foreach ($catalog as $sp => $tiers) foreach ($tiers as $tier => $items) printf("%s %-12s %2d servicii, puncte total=%d\n", $sp, $tier, count($items), array_sum(array_map(fn($i) => $i['subscription_points'] * $i['qty'], $items)));
