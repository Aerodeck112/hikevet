<?php
// Extrage contract_template_lines() din simulator in JSON, fara a modifica textul (inclusiv greselile de tipar din sursa).
$src = file_get_contents(__DIR__ . '/../../source-wordpress/hikevet-abonament-simulator/hikevet-abonament-simulator.php');
if (!preg_match('/private function contract_template_lines\(\)\{\s*return (array\(.*?\n\s*\));/s', $src, $m)) { fwrite(STDERR, "sablon negasit\n"); exit(1); }
$stripped = preg_replace('/"(?:[^"\\\\]|\\\\.)*"/', '', $m[1]);
if (preg_match('/[a-zA-Z_\$;`]/', str_replace('array(', '', $stripped))) { fwrite(STDERR, "literal nesigur\n"); exit(1); }
$lines = eval('return ' . $m[1] . ';');
file_put_contents(__DIR__ . '/../../database/seeders/data/contract-template-2.7.0.json', json_encode($lines, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
echo count($lines), " linii\n", $lines[0], "\n", end($lines), "\n";
