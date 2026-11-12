<?php

namespace App\Services\Import;

use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

/**
 * Acces doar in citire la copia bazei WordPress. Prefixul tabelelor este detectat automat
 * (nu este obligatoriu "wp_").
 */
class WordPressSource
{
    private ?string $prefix = null;

    public function __construct(private readonly string $connection = 'wp') {}

    public function db(): ConnectionInterface
    {
        return DB::connection($this->connection);
    }

    /** @return list<string> */
    public function tables(): array
    {
        return array_map(fn ($t) => is_array($t) ? $t['name'] : $t, Schema::connection($this->connection)->getTableListing(schemaQualified: false));
    }

    public function prefix(): string
    {
        if ($this->prefix !== null) {
            return $this->prefix;
        }

        foreach ($this->tables() as $table) {
            foreach (['hv_st_cases', 'usermeta', 'postmeta'] as $marker) {
                if (str_ends_with($table, $marker)) {
                    return $this->prefix = substr($table, 0, -strlen($marker));
                }
            }
        }

        throw new RuntimeException('Nu am găsit tabele WordPress în baza sursă. Verifică setările WP_DB_* din .env.');
    }

    public function has(string $table): bool
    {
        return in_array($this->prefix().$table, $this->tables(), true);
    }

    public function table(string $table)
    {
        return $this->db()->table($this->prefix().$table);
    }

    /** @return list<string> */
    public function columns(string $table): array
    {
        return Schema::connection($this->connection)->getColumnListing($this->prefix().$table);
    }

    /** Valoare meta WordPress: deserializeaza in siguranta array-urile PHP (fara obiecte). */
    public static function meta(mixed $value): mixed
    {
        if (! is_string($value) || $value === '') {
            return $value;
        }
        if (preg_match('/^(a|s|i|b|d):/', $value) && str_ends_with(rtrim($value), ';') || str_ends_with(rtrim($value), '}')) {
            $decoded = @unserialize($value, ['allowed_classes' => false]);
            if ($decoded !== false || $value === 'b:0;') {
                return $decoded;
            }
        }

        return $value;
    }

    /** O data MySQL valida sau null ("0000-00-00" si valorile goale nu sunt date). */
    public static function date(mixed $value): ?string
    {
        $value = is_string($value) ? trim($value) : '';
        if ($value === '' || str_starts_with($value, '0000-00-00')) {
            return null;
        }
        $ts = strtotime($value);

        return $ts ? date(strlen($value) > 10 ? 'Y-m-d H:i:s' : 'Y-m-d', $ts) : null;
    }
}
