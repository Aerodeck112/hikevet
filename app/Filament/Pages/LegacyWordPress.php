<?php

namespace App\Filament\Pages;

use App\Enums\Permission;
use App\Models\LegacyFile;
use App\Models\LegacyRow;
use App\Models\LegacyTable;
use App\Services\Legacy\LegacyArchive;
use App\Support\Audit;
use BackedEnum;
use Filament\Pages\Page;
use Livewire\Attributes\Url;
use UnitEnum;

/** Consultarea arhivei brute a site-ului WordPress: orice tabela, orice rand, doar citire. */
class LegacyWordPress extends Page
{
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-archive-box';

    protected static string|UnitEnum|null $navigationGroup = 'Sistem';

    protected static ?string $navigationLabel = 'Arhivă WordPress';

    protected static ?string $title = 'Arhiva site-ului WordPress vechi';

    protected static ?int $navigationSort = 35;

    protected static ?string $slug = 'arhiva-wordpress';

    protected string $view = 'filament.pages.legacy-wordpress';

    #[Url]
    public ?int $table = null;

    #[Url]
    public int $page = 1;

    public string $term = '';

    public ?array $search = null;

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->can(Permission::LEGACY_VIEW);
    }

    public function updatedTable(): void
    {
        $this->page = 1;
        $this->search = null;
        $this->term = '';
    }

    public function runSearch(int $from = 0): void
    {
        $table = LegacyTable::query()->findOrFail($this->table);
        $result = app(LegacyArchive::class)->search($table, $this->term, $from);
        $this->search = ['term' => $this->term, 'from' => $from] + $result;
        Audit::log('legacy.searched', null, 'Căutare în arhiva WordPress, tabela '.$table->name);
    }

    public function clearSearch(): void
    {
        $this->search = null;
        $this->term = '';
    }

    public function exportCsv()
    {
        abort_unless(auth()->user()->can(Permission::LEGACY_EXPORT), 403);
        $table = LegacyTable::query()->findOrFail($this->table);
        Audit::log('legacy.exported', null, 'Export CSV din arhiva WordPress: '.$table->name.' ('.$table->row_count.' rânduri)');

        return response()->streamDownload(function () use ($table) {
            $out = fopen('php://output', 'wb');
            app(LegacyArchive::class)->writeCsv($table, $out);
            fclose($out);
        }, 'arhiva-'.$table->name.'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function downloadFile(int $id)
    {
        abort_unless(auth()->user()->can(Permission::LEGACY_EXPORT), 403);
        $file = LegacyFile::query()->findOrFail($id);
        Audit::log('legacy.file_downloaded', $file, 'Descărcare din arhiva WordPress: '.$file->label);

        return response()->streamDownload(function () use ($file) {
            app(LegacyArchive::class)->decryptFile($file->path, function (string $chunk) {
                echo $chunk;
            });
        }, $file->kind === 'wordpress-sql' ? 'wordpress-original.sql' : basename($file->label), ['Content-Type' => 'application/octet-stream']);
    }

    protected function getViewData(): array
    {
        $tables = LegacyTable::query()->orderBy('name')->get(['id', 'name', 'row_count', 'snapshotted_at', 'sha256']);
        $current = $this->table ? LegacyTable::query()->find($this->table) : null;
        $rows = collect();
        $pages = 1;
        if ($current && ! $this->search) {
            $perPage = 25;
            $pages = max(1, (int) ceil($current->row_count / $perPage));
            $this->page = min(max(1, $this->page), $pages);
            $rows = LegacyRow::query()->where('legacy_table_id', $current->id)->orderBy('ordinal')
                ->forPage($this->page, $perPage)->get()->map(fn (LegacyRow $r) => ['ordinal' => $r->ordinal, 'pk' => $r->pk, 'values' => $r->values()]);
        }

        return [
            'tables' => $tables,
            'current' => $current,
            'rows' => $this->search ? collect($this->search['matches']) : $rows,
            'pages' => $pages,
            'files' => LegacyFile::query()->latest('id')->get(),
            'canExport' => auth()->user()->can(Permission::LEGACY_EXPORT),
            'totalRows' => $tables->sum('row_count'),
        ];
    }
}
