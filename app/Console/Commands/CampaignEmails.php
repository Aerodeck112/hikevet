<?php

namespace App\Console\Commands;

use App\Services\Campaign\CampaignAppointments;
use App\Services\Campaign\CampaignMailer;
use App\Support\Settings;
use Illuminate\Console\Command;

/** Motorul orar al campaniei (cron-urile hv_st_hourly_appointment_reminders si hv_st_hourly_postop_emails din plugin). */
class CampaignEmails extends Command
{
    protected $signature = 'hikevet:campaign-emails';

    protected $description = 'Trimite reamintirile programărilor din campanie și emailurile de follow-up la 48 de ore.';

    public function handle(CampaignAppointments $appointments, CampaignMailer $mailer): int
    {
        if (! CampaignMailer::active()) {
            $this->info('Campania de sterilizare este oprită: nu se trimite nimic.');

            return self::SUCCESS;
        }
        $reminders = $appointments->runReminders();
        $followups = $mailer->runFollowups();
        Settings::set('campaign.last_email_run', now()->toIso8601String());
        $this->info(sprintf('Reamintiri: %d trimise, %d eșuate. Follow-up: %d trimise, %d eșuate, %d oprite după %d încercări.',
            $reminders['sent'], $reminders['failed'], $followups['sent'], $followups['failed'], $followups['stopped_after_retries'], CampaignMailer::MAX_AUTOMATIC_FOLLOWUP_ATTEMPTS));

        return self::SUCCESS;
    }
}
