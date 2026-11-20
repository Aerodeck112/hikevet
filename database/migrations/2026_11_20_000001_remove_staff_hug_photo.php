<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Fotografia 0h7a9586 (membră a echipei cu un câine) a fost scoasă de pe site la cererea clinicii.
 * Fișierul nu mai există în pachet; aici se scot și referințele din conținutul deja instalat.
 */
return new class extends Migration
{
    private const NEEDLE = '0h7a9586-scaled-e1689027689830';

    public function up(): void
    {
        foreach (DB::table('pages')->where('blocks', 'like', '%'.self::NEEDLE.'%')->get(['id', 'blocks']) as $page) {
            $blocks = json_decode((string) $page->blocks, true) ?: [];
            $blocks = array_filter($blocks, fn ($value) => ! (is_string($value) && str_contains($value, self::NEEDLE)));
            DB::table('pages')->where('id', $page->id)->update(['blocks' => json_encode($blocks, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)]);
        }
        foreach (['pages' => 'hero_image', 'posts' => 'image', 'team_members' => 'photo'] as $table => $column) {
            DB::table($table)->where($column, 'like', '%'.self::NEEDLE.'%')->update([$column => null]);
        }
        if (DB::getSchemaBuilder()->hasTable('media')) {
            DB::table('media')->where('path', 'like', '%'.self::NEEDLE.'%')->delete();
        }
    }

    public function down(): void {}
};
