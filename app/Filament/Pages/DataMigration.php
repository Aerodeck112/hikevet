<?php

namespace App\Filament\Pages;

use App\Enums\Permission;
use App\Models\ImportBatch;
use App\Services\Import\SterilizationImporter;
use App\Services\Import\WordPressCoreImporter;
use App\Services\Import\WordPressSource;
use App\Support\Audit;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\DB;
use Throwable;
use UnitEnum;

/** Starea migrarii din WordPress: sursa asteptata, probe (dry-run), loturi, checksum, erori si totaluri. */
class DataMigration extends Page
{
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-arrow-down-on-square-stack';

    protected static string|UnitEnum|null $navigationGroup = 'Sistem';

    protected static ?string $navigationLabel = 'Migrare date';

    protected static ?string $title = 'Migrare date din WordPress';

    protected static ?int $navigationSort = 30;

    protected string $view = 'filament.pages.data-migration';

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->can(Permission::IMPORT_RUN);
    }

    private function source(): ?WordPressSource
    {
        if (blank(config('database.connections.wp.database'))) {
            return null;
        }
        try {
            $source = new WordPressSource('wp');
            $source->prefix();

            return $source;
        } catch (Throwable) {
            return null;
        }
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('dryRun')->label('Rulează proba (dry-run)')->icon('heroicon-o-beaker')
                ->visible(fn () => $this->source() !== null)
                ->action(function () {
                    $source = $this->source();
                    (new WordPressCoreImporter($source))->run(true, auth()->id());
                    (new SterilizationImporter($source))->run(true, config('hikevet.wp_uploads_path'), auth()->id());
                    Audit::log('import.dry_run', null, 'Probă de import rulată din admin');
                    Notification::make()->title('Proba s-a încheiat. Nu s-a scris nimic.')->success()->send();
                }),
        ];
    }

    protected function getViewData(): array
    {
        $source = $this->source();
        $preflight = null;
        if ($source) {
            try {
                $preflight = (new SterilizationImporter($source))->preflight();
            } catch (Throwable $e) {
                $preflight = ['error' => $e->getMessage()];
            }
        }

        return [
            'connected' => $source !== null,
            'preflight' => $preflight,
            'batches' => ImportBatch::query()->latest('id')->limit(12)->get(),
            'expected' => [
                'Tabele campanie' => 'hv_st_cases, hv_st_appointments, hv_st_email_log',
                'Tabele Wallet' => 'hv_wallet_tx, hv_treatments, hv_notifications, hv_email_log, hv_audit_log, hv_doctor_notes',
                'Tabele WordPress' => 'users, usermeta (inclusiv hv_pets, hv_contracts), posts și postmeta (hv_abon_sub, hv_consent), options (cheile hv_*)',
                'Fișiere' => 'wp-content/uploads (fotografii pacienți, acte scanate, contracte PDF)',
            ],
            'archive' => ['Dosare în arhivă' => DB::table('sterilization_cases')->count(), 'Documente păstrate' => DB::table('sterilization_documents')->where('is_missing', false)->count(),
                'Documente lipsă în sursă' => DB::table('sterilization_documents')->where('is_missing', true)->count(), 'Clienți migrați' => DB::table('users')->whereNotNull('wp_user_id')->count(),
                'Consimțăminte migrate' => DB::table('consents')->whereNotNull('wp_post_id')->count()],
        ];
    }
}
