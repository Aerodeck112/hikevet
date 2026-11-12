<?php

namespace App\Support;

use App\Models\Setting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;

/** Setari editabile din admin, cu valori implicite luate din site-ul si pluginurile existente. */
class Settings
{
    public const DEFAULTS = [
        'clinic.name' => 'HiKeVet',
        'clinic.legal_name' => 'S.C. FEST4PETS S.R.L.',
        'clinic.tagline' => 'Oferim servicii medicale de înaltă calitate și îngrijire personalizată.',
        'clinic.phone' => '+40 771 280 345',
        'clinic.email' => 'contact@hikevet.com',
        'clinic.address' => 'Str. Băneasa nr. 23',
        'clinic.city' => 'Târgu Mureș',
        'clinic.maps_query' => 'HiKeVet, Strada Băneasa 23, Târgu Mureș',
        'clinic.review_url' => 'https://g.page/r/CXkK4U8d4SSqEBM/review',
        'clinic.instagram' => '',
        'clinic.facebook' => '',
        'clinic.tiktok' => '',
        'clinic.hours' => [],
        'booking.digitail_url' => 'https://vet.digitail.io/clinics/clinica-veterinara-hikevet',
        'booking.mode' => 'digitail',
        'mail.clinic_recipients' => ['contact@hikevet.com'],
        'mail.sterilization_recipients' => ['site@hikevet.com'],
        'subscriptions.expiry_notice_days' => 5,
        'consent.code_ttl_hours' => 12,
        'consent.max_attempts' => 5,
        'marketing.gtm_id' => '',
        'marketing.ga4_id' => '',
        'marketing.search_console_verification' => '',
        'marketing.google_ads_id' => '',
        'seo.robots_extra' => '',
        'seo.default_description' => 'Clinică veterinară în Târgu Mureș: consultații, imagistică, chirurgie, abonamente de sănătate pentru câini și pisici.',
    ];

    public static function get(string $key, mixed $default = null): mixed
    {
        $all = self::all();

        return $all[$key] ?? self::DEFAULTS[$key] ?? $default;
    }

    public static function set(string $key, mixed $value, bool $encrypted = false): void
    {
        Setting::updateOrCreate(['key' => $key], [
            'group' => explode('.', $key)[0],
            'value' => ['v' => $encrypted ? Crypt::encryptString(json_encode($value)) : $value],
            'is_encrypted' => $encrypted,
        ]);
        Cache::forget('settings.all');
    }

    /** @return array<string, mixed> */
    public static function all(): array
    {
        try {
            return Cache::rememberForever('settings.all', function () {
                return Setting::all()->mapWithKeys(function (Setting $s) {
                    $value = $s->value['v'] ?? null;
                    if ($s->is_encrypted && is_string($value)) {
                        $value = json_decode(Crypt::decryptString($value), true);
                    }

                    return [$s->key => $value];
                })->all();
            });
        } catch (\Throwable) {
            // Inainte de instalare tabela nu exista inca.
            return [];
        }
    }

    /** @return list<string> */
    public static function emails(string $key): array
    {
        return array_values(array_filter(array_map('trim', (array) self::get($key, [])), fn ($e) => filter_var($e, FILTER_VALIDATE_EMAIL)));
    }
}
