<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Filament\Pages\LegacyWordPress;
use App\Models\AuditLog;
use App\Models\LegacyFile;
use App\Models\LegacyRow;
use App\Models\LegacyTable;
use App\Services\Import\WordPressSource;
use App\Services\Legacy\LegacyArchive;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use LogicException;
use Tests\TestCase;

/** Arhiva bruta a bazei WordPress, pe o sursa sintetica. */
class LegacyArchiveTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedBusiness();
        config(['database.connections.wp' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '', 'foreign_key_constraints' => false]]);
        DB::purge('wp');
        $schema = Schema::connection('wp');
        $schema->create('wpz_usermeta', fn (Blueprint $t) => [$t->id('umeta_id'), $t->unsignedBigInteger('user_id'), $t->string('meta_key'), $t->text('meta_value')->nullable()]);
        $schema->create('wpz_options', fn (Blueprint $t) => [$t->id('option_id'), $t->string('option_name'), $t->text('option_value')->nullable()]);
        $schema->create('wpz_plugin_fara_ecran', fn (Blueprint $t) => [$t->string('cod'), $t->binary('blob')->nullable()]);
        $wp = DB::connection('wp');
        $wp->table('wpz_usermeta')->insert(['user_id' => 5, 'meta_key' => 'hv_pets', 'meta_value' => serialize([['pet_name' => 'Ursu']])]);
        $wp->table('wpz_options')->insert(['option_name' => 'hv_abon_settings', 'option_value' => serialize(['contract_valid_days' => 5])]);
        foreach (range(1, 30) as $i) {
            $wp->table('wpz_options')->insert(['option_name' => 'optiune_'.$i, 'option_value' => 'valoare sintetică '.$i]);
        }
        $wp->table('wpz_plugin_fara_ecran')->insert(['cod' => 'CNP-SINTETIC-1900101000000', 'blob' => "\xff\xfe\x00binar"]);
    }

    private function archive(): LegacyArchive
    {
        return app(LegacyArchive::class);
    }

    public function test_every_table_and_row_is_copied_encrypted_and_reads_back_identically(): void
    {
        $stats = $this->archive()->snapshot(new WordPressSource('wp'));

        $this->assertSame(['tables' => 3, 'rows' => 33], $stats);
        $table = LegacyTable::query()->where('name', 'wpz_plugin_fara_ecran')->sole();
        $this->assertSame(['cod', 'blob'], $table->columns);
        $row = LegacyRow::query()->where('legacy_table_id', $table->id)->sole();

        // Criptat in baza noua: textul nu apare in clar.
        $this->assertStringNotContainsString('CNP-SINTETIC', $row->data);
        // Inclusiv valorile binare revin octet cu octet.
        $this->assertSame(['cod' => 'CNP-SINTETIC-1900101000000', 'blob' => "\xff\xfe\x00binar"], $row->values());
        $this->assertSame([], $this->archive()->verify($table));
        $this->assertSame('1', LegacyRow::query()->where('legacy_table_id', LegacyTable::query()->where('name', 'wpz_options')->value('id'))->orderBy('ordinal')->value('pk'));
    }

    public function test_new_snapshot_replaces_the_previous_copy_and_rows_cannot_be_edited(): void
    {
        $this->archive()->snapshot(new WordPressSource('wp'));
        DB::connection('wp')->table('wpz_options')->insert(['option_name' => 'adaugata_dupa', 'option_value' => 'x']);
        $this->archive()->snapshot(new WordPressSource('wp'));

        $this->assertSame(32, LegacyTable::query()->where('name', 'wpz_options')->value('row_count'));
        $this->assertSame(34, LegacyRow::query()->count());

        $row = LegacyRow::query()->first();
        $this->expectException(LogicException::class);
        $row->update(['pk' => 'altceva']);
    }

    public function test_tampering_is_detected_and_search_finds_text_inside_encrypted_rows(): void
    {
        $this->archive()->snapshot(new WordPressSource('wp'));
        $options = LegacyTable::query()->where('name', 'wpz_options')->sole();

        $result = $this->archive()->search($options, 'contract_valid_days');
        $this->assertCount(1, $result['matches']);
        $this->assertSame('hv_abon_settings', $result['matches'][0]['values']['option_name']);

        // Cautare pe bucati: continuarea porneste de unde s-a oprit.
        $first = $this->archive()->search($options, 'valoare sintetică', 0, 10);
        $this->assertCount(10, $first['matches']);
        $second = $this->archive()->search($options, 'valoare sintetică', $first['next'], 50);
        $this->assertCount(20, $second['matches']);

        $victim = LegacyRow::query()->where('legacy_table_id', $options->id)->where('ordinal', 3)->sole();
        DB::table('legacy_rows')->where('id', $victim->id)->update(['data' => Crypt::encryptString('{"option_name":"falsificat"}')]);
        $this->assertSame([3], $this->archive()->verify($options));
    }

    public function test_original_sql_file_is_kept_encrypted_and_restored_byte_for_byte(): void
    {
        $sql = tempnam(sys_get_temp_dir(), 'sql');
        file_put_contents($sql, str_repeat("INSERT INTO `wp_hv_st_cases` VALUES (1,'HV-ST-2026-001');\n", 40000));
        $file = $this->archive()->storeFile($sql, 'wordpress-sql', 'Baza originală');

        $this->assertSame(hash_file('sha256', $sql), $file->sha256);
        $this->assertStringNotContainsString('HV-ST-2026-001', Storage::disk('private')->get($file->path));
        $restored = '';
        $this->archive()->decryptFile($file->path, function (string $chunk) use (&$restored) {
            $restored .= $chunk;
        });
        $this->assertSame(file_get_contents($sql), $restored);

        // Fisierul alterat nu se mai poate citi.
        $bytes = Storage::disk('private')->get($file->path);
        Storage::disk('private')->put($file->path, substr($bytes, 0, -40).str_repeat('x', 40));
        $this->expectException(\RuntimeException::class);
        $this->archive()->decryptFile($file->path, fn () => null);
        @unlink($sql);
    }

    public function test_only_admins_see_the_archive_and_exports_are_logged(): void
    {
        $this->archive()->snapshot(new WordPressSource('wp'));
        $table = LegacyTable::query()->where('name', 'wpz_plugin_fara_ecran')->sole();

        $this->actingAs($this->staff(Role::Reception))->get('/admin/arhiva-wordpress')->assertForbidden();
        $this->actingAs($this->staff(Role::Doctor))->get('/admin/arhiva-wordpress')->assertForbidden();

        $admin = $this->staff(Role::Admin);
        $this->actingAs($admin)->get('/admin/arhiva-wordpress?table='.$table->id)->assertOk()->assertSee('CNP-SINTETIC-1900101000000');

        Livewire::actingAs($admin)->test(LegacyWordPress::class, ['table' => $table->id])->call('exportCsv')->assertFileDownloaded('arhiva-wpz_plugin_fara_ecran.csv');
        $this->assertTrue(AuditLog::query()->where('action', 'legacy.exported')->exists());
    }

    public function test_import_command_copies_the_archive_and_keeps_the_sql_dump(): void
    {
        $sql = tempnam(sys_get_temp_dir(), 'sql');
        file_put_contents($sql, "-- dump sintetic\n");

        $this->artisan('hikevet:import-wordpress', ['--only' => 'legacy'])->assertSuccessful();
        $this->assertSame(0, LegacyTable::query()->count());

        $this->artisan('hikevet:import-wordpress', ['--only' => 'legacy', '--execute' => true, '--dump' => $sql, '--skip-legacy' => ['options']])->assertSuccessful();
        $this->assertSame(['wpz_plugin_fara_ecran', 'wpz_usermeta'], LegacyTable::query()->orderBy('name')->pluck('name')->all());
        $this->assertSame(1, LegacyFile::query()->count());
        @unlink($sql);
    }
}
