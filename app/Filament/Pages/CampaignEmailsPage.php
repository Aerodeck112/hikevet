<?php

namespace App\Filament\Pages;

use App\Enums\Permission;
use App\Models\SterilizationCase;
use App\Models\SterilizationEmailLog;
use App\Services\Campaign\CampaignMailer;
use App\Support\Settings;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Artisan;
use Livewire\Attributes\Url;
use UnitEnum;

/** Emailurile postoperatorii si de follow-up (render_postop_emails, render_followup_emails din plugin). */
class CampaignEmailsPage extends Page
{
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-envelope';

    protected static string|UnitEnum|null $navigationGroup = 'ERP';

    protected static ?string $navigationLabel = 'Emailuri campanie';

    protected static ?string $title = 'Emailuri campanie sterilizare';

    protected static ?int $navigationSort = 92;

    protected static ?string $navigationParentItem = 'Campanie sterilizare (arhivă)';

    protected static ?string $slug = 'campanie/emailuri';

    protected string $view = 'filament.campaign.emails';

    #[Url]
    public string $show = 'pending';

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->can(Permission::CAMPAIGN_MANAGE);
    }

    protected function getHeaderActions(): array
    {
        return [Action::make('run')->label('Verifică acum emailurile scadente')->icon('heroicon-o-arrow-path')->requiresConfirmation()
            ->modalDescription('Trimite acum reamintirile și follow-up-urile ajunse la scadență. Același lucru îl face automat sarcina programată, din oră în oră.')
            ->action(function () {
                Artisan::call('hikevet:campaign-emails');
                Notification::make()->title(trim(Artisan::output()))->success()->send();
            })];
    }

    protected function getViewData(): array
    {
        $cases = SterilizationCase::query()->active()->whereNotNull('data_externare')->orderByDesc('data_externare')->orderByDesc('id')->get();
        $rows = $cases->map(function (SterilizationCase $c) {
            $r = $c->row();

            return ['id' => $c->id, 'code' => $r['cod_caz'], 'patient' => $r['nume_pacient'], 'owner' => $r['nume_proprietar'], 'email' => $r['email'],
                'discharge' => trim($r['data_externare'].' '.$r['ora_externare']), 'due' => CampaignMailer::postopDueAt($r),
                'final_status' => $r['final_summary_email_status'], 'final_at' => $r['final_summary_email_sent_at'], 'final_error' => $r['final_summary_email_error'],
                'follow_status' => $r['postop_email_status'], 'follow_at' => $r['postop_email_sent_at'], 'follow_error' => $r['postop_email_error']];
        });
        $failedFollowups = SterilizationEmailLog::query()->where('email_type', 'followup_48h_auto')->where('status', 'failed')
            ->selectRaw('sterilization_case_id, COUNT(*) AS n')->groupBy('sterilization_case_id')->pluck('n', 'sterilization_case_id');
        if ($this->show === 'pending') {
            $rows = $rows->filter(fn ($r) => $r['final_status'] !== 'sent' || $r['follow_at'] === '' || $r['follow_at'] === null);
        }

        return ['rows' => $rows->take(300), 'total' => $cases->count(), 'failedFollowups' => $failedFollowups, 'lastRun' => Settings::get('campaign.last_email_run'),
            'maxAttempts' => CampaignMailer::MAX_AUTOMATIC_FOLLOWUP_ATTEMPTS];
    }
}
