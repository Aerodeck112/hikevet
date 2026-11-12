<?php

namespace App\Filament\Pages;

use App\Enums\Permission;
use App\Models\UpdateRun;
use App\Services\Backup\BackupService;
use App\Services\Updates\UpdateService;
use App\Support\Audit;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Throwable;
use UnitEnum;

/** Actualizari platforma si starea sistemului. Doar Super Admin. */
class PlatformUpdates extends Page
{
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-arrow-path';

    protected static string|UnitEnum|null $navigationGroup = 'Sistem';

    protected static ?string $navigationLabel = 'Actualizări și sănătate';

    protected static ?string $title = 'Actualizări platformă';

    protected static ?int $navigationSort = 40;

    protected string $view = 'filament.pages.platform-updates';

    /** Pachetul incarcat si verificat, in asteptarea confirmarii. */
    public ?array $pending = null;

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->can(Permission::PLATFORM_UPDATE);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('upload')->label('Încarcă pachet de actualizare')->icon('heroicon-o-arrow-up-tray')
                ->modalDescription('Sunt acceptate doar pachetele semnate cu cheia configurată pe server. Încărcarea doar verifică pachetul; instalarea se confirmă separat.')
                ->schema([FileUpload::make('package')->label('Fișier ZIP')->disk('private')->directory('updates')->acceptedFileTypes(['application/zip', 'application/x-zip-compressed'])->maxSize(204800)->required()])
                ->action(function (array $data) {
                    $path = Storage::disk('private')->path($data['package']);
                    try {
                        $preflight = app(UpdateService::class)->preflight($path);
                        $this->pending = ['file' => $data['package'], 'version' => $preflight['manifest']['version'], 'changelog' => $preflight['manifest']['changelog'] ?? '',
                            'has_migrations' => (bool) ($preflight['manifest']['has_migrations'] ?? false), 'files' => count($preflight['manifest']['files']),
                            'bytes' => $preflight['bytes'], 'sha256' => $preflight['sha256'], 'checks' => $preflight['checks'], 'ok' => $preflight['ok']];
                    } catch (Throwable $e) {
                        Storage::disk('private')->delete($data['package']);
                        $this->pending = null;
                        Audit::log('update.rejected', null, 'Pachet de actualizare respins: '.mb_substr($e->getMessage(), 0, 200));
                        Notification::make()->title('Pachetul a fost respins')->body($e->getMessage())->danger()->persistent()->send();
                    }
                }),
            Action::make('backup')->label('Backup acum')->icon('heroicon-o-circle-stack')->color('gray')
                ->action(function () {
                    $result = app(BackupService::class)->create('manual');
                    Audit::log('backup.created', null, 'Backup manual: '.basename($result['path']));
                    Notification::make()->title('Backup creat: '.basename($result['path']))->success()->send();
                }),
        ];
    }

    public function install(): void
    {
        abort_unless(static::canAccess() && $this->pending && $this->pending['ok'], 403);

        $run = app(UpdateService::class)->apply(Storage::disk('private')->path($this->pending['file']), auth()->id());
        Audit::log('update.applied', $run, 'Actualizare '.$run->from_version.' → '.$run->to_version.': '.$run->status);
        Storage::disk('private')->delete($this->pending['file']);
        $this->pending = null;

        $run->status === 'succeeded'
            ? Notification::make()->title('Actualizarea a fost instalată')->success()->send()
            : Notification::make()->title('Actualizarea a eșuat și a fost anulată')->body('Versiunea anterioară a fost restaurată. Vezi raportul de mai jos.')->danger()->persistent()->send();
    }

    public function discard(): void
    {
        if ($this->pending) {
            Storage::disk('private')->delete($this->pending['file']);
        }
        $this->pending = null;
    }

    protected function getViewData(): array
    {
        $cron = Cache::get('hikevet.last_cron_run');
        $health = [];
        $check = function (string $label, callable $test) use (&$health) {
            try {
                [$ok, $detail] = $test();
            } catch (Throwable $e) {
                [$ok, $detail] = [false, mb_substr($e->getMessage(), 0, 120)];
            }
            $health[] = compact('label', 'ok', 'detail');
        };
        $backups = app(BackupService::class)->list();

        $check('Bază de date', fn () => [true, DB::getDriverName().' '.DB::scalar('select version()')]);
        $check('Sarcini programate (cron)', fn () => [$cron && now()->diffInMinutes($cron, true) < 10, $cron ? 'ultima rulare '.\Illuminate\Support\Carbon::parse($cron)->diffForHumans() : 'nu a rulat niciodată: adaugă sarcina cron din INSTALL_CPANEL.md']);
        $check('Email', fn () => [config('mail.default') !== 'log', config('mail.default') === 'log' ? 'emailurile se scriu doar în jurnal (SMTP neconfigurat)' : 'SMTP: '.config('mail.mailers.smtp.host')]);
        $check('Spațiu pe disc', fn () => [($free = disk_free_space(base_path())) > 300 * 1048576, round($free / 1048576).' MB liberi']);
        $check('Ultimul backup', fn () => [$backups && $backups[0]['created'] > time() - 36 * 3600, $backups ? date('d.m.Y H:i', $backups[0]['created']).' ('.round($backups[0]['bytes'] / 1024).' KB)' : 'nu există niciun backup']);
        $check('Coada de sarcini', fn () => [DB::table('failed_jobs')->count() === 0, DB::table('jobs')->count().' în așteptare, '.DB::table('failed_jobs')->count().' eșuate']);
        $check('Mod depanare', fn () => [! config('app.debug') || ! app()->isProduction(), config('app.debug') ? 'APP_DEBUG este activ'.(app()->isProduction() ? ': dezactivează-l în producție' : ' (mediu de test)') : 'dezactivat']);
        $check('Cheie pentru actualizări', fn () => [filled(config('hikevet.update_public_key')), filled(config('hikevet.update_public_key')) ? 'configurată' : 'lipsește: actualizările ZIP sunt refuzate']);

        return ['version' => config('hikevet.version'), 'php' => PHP_VERSION, 'laravel' => app()->version(), 'health' => $health,
            'runs' => UpdateRun::query()->latest('id')->limit(8)->get(), 'backups' => array_slice($backups, 0, 8)];
    }
}
