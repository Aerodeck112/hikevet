<?php

namespace Tests\Feature;

use App\Models\Service;
use App\Support\JsStrings;
use Database\Seeders\SiteContentSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Site-ul in trei limbi: romana la radacina, engleza (/en) si maghiara (/hu), cu continut si interfata traduse. */
class I18nTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedBusiness();
        $this->seed(SiteContentSeeder::class);
    }

    public function test_romanian_stays_at_the_root_and_other_languages_have_a_prefix(): void
    {
        $this->get('/')->assertOk()->assertSee('<html lang="ro"', false)->assertSee('Programează-te');
        $this->get('/en/')->assertOk()->assertSee('<html lang="en"', false)->assertSee('Book an appointment')->assertDontSee('>Programează-te<', false);
        $this->get('/hu/')->assertOk()->assertSee('<html lang="hu"', false)->assertSee('Időpontot kérek');

        // Legaturile generate pastreaza limba: meniul, butonul de programare, selectorul de limba
        $en = $this->get('/en/servicii/')->assertOk()->getContent();
        $this->assertStringContainsString('/en/abonamente/', $en);
        $this->assertStringContainsString('/en/programare/', $en);
        $this->assertStringContainsString('hreflang="hu" lang="hu"', $en);
    }

    public function test_seo_canonical_hreflang_and_sitemap_cover_all_languages(): void
    {
        $html = $this->get('/en/servicii/')->getContent();
        $this->assertStringContainsString('<link rel="canonical" href="http://localhost/en/servicii/">', $html);
        $this->assertStringContainsString('hreflang="ro" href="http://localhost/servicii/"', $html);
        $this->assertStringContainsString('hreflang="hu" href="http://localhost/hu/servicii/"', $html);
        $this->assertStringContainsString('hreflang="x-default" href="http://localhost/servicii/"', $html);
        $this->assertStringContainsString('content="en_GB"', $html);

        $sitemap = $this->get('/sitemap.xml')->assertOk()->getContent();
        $this->assertStringContainsString('<loc>http://localhost/hu/ecografie/</loc>', $sitemap);
        $this->assertStringContainsString('hreflang="en" href="http://localhost/en/ecografie/"', $sitemap);
    }

    public function test_content_is_translated_from_the_translations_table(): void
    {
        $en = json_decode(file_get_contents(database_path('seeders/data/i18n/en-services.json')), true)['services']['ecografie']['name'];
        $hu = json_decode(file_get_contents(database_path('seeders/data/i18n/hu-services.json')), true)['services']['ecografie']['name'];
        $this->get('/ecografie/')->assertSee('Ecografie');
        $this->get('/en/ecografie/')->assertSee($en)->assertSee('The vet gives Hiko an ultrasound scan');
        $this->get('/hu/ecografie/')->assertSee($hu);

        // Fara traducere, campul ramane in romana; in admin (romana) modelul e neschimbat
        $service = Service::query()->where('slug', 'ecografie')->first();
        $this->assertSame('Ecografie', $service->name);
        app()->setLocale('en');
        $this->assertSame($en, $service->name);
        app()->setLocale('ro');

        $this->get('/en/termeni-si-conditii-abonamente/')->assertOk()->assertSee('informative translation', false);
    }

    public function test_admin_and_assets_are_never_localized(): void
    {
        $this->get('/en/admin/login')->assertNotFound();
        $this->get('/admin/login')->assertOk();
    }

    public function test_validation_messages_exist_in_every_language(): void
    {
        app()->setLocale('ro');
        $this->assertSame('Completează emailul.', __('validation.required', ['attribute' => 'emailul']));
        app()->setLocale('hu');
        $this->assertStringStartsWith('Töltsd ki', __('validation.required', ['attribute' => 'név']));
    }

    public function test_every_interface_string_has_english_and_hungarian_translations(): void
    {
        $en = json_decode(file_get_contents(lang_path('en.json')), true);
        $hu = json_decode(file_get_contents(lang_path('hu.json')), true);
        $dirs = ['layouts', 'site', 'account', 'booking', 'partials', 'components', 'errors'];
        $missing = [];
        foreach ($dirs as $dir) {
            foreach (glob(resource_path("views/$dir/*.blade.php")) as $file) {
                preg_match_all("/__\\('((?:[^'\\\\]|\\\\.)*)'/u", file_get_contents($file), $m);
                foreach ($m[1] as $key) {
                    $key = str_replace("\\'", "'", $key);
                    if (! isset($en[$key]) || ! isset($hu[$key])) {
                        $missing[] = basename($file).': '.$key;
                    }
                }
            }
        }
        foreach (JsStrings::KEYS as $key) {
            if (! isset($en[$key]) || ! isset($hu[$key])) {
                $missing[] = 'js: '.$key;
            }
        }
        $this->assertSame([], array_values(array_unique($missing)));

        // Toate textele din scripturi sunt in lista trimisa paginii
        $js = '';
        foreach (glob(resource_path('js/*.js')) as $file) {
            $js .= file_get_contents($file);
        }
        preg_match_all("/\\bt\\('((?:[^'\\\\]|\\\\.)*)'/u", $js, $m);
        $this->assertSame([], array_values(array_diff(array_unique($m[1]), JsStrings::KEYS)));
    }

    public function test_booking_from_the_english_site_stays_in_english(): void
    {
        $form = ['date' => now()->addDays(3)->toDateString(), 'preferred_period' => 'dimineata', 'client_name' => 'Jane Doe', 'client_phone' => '0700000009',
            'client_email' => 'jane@example.com', 'pet_name' => 'Max', 'pet_species' => 'Câine', 'consent' => '1'];
        $res = $this->post('/en/programare', $form);
        $res->assertRedirect();
        $this->assertStringContainsString('/en/programare/confirmare/', $res->headers->get('Location'));
    }
}
