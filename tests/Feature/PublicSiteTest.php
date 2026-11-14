<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Http\Middleware\PublicRedirects;
use App\Models\ContactMessage;
use App\Models\Page;
use App\Models\Post;
use App\Models\Service;
use App\Support\Settings;
use Database\Seeders\SiteContentSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

/** Site-ul public: URL-urile din WordPress raman valabile, continutul real este afisat, SEO-ul tehnic este corect. */
class PublicSiteTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedBusiness();
        $this->seed(SiteContentSeeder::class);
    }

    /** Toate adresele din sitemap-ul WordPress (wp-sitemap-posts-page-1.xml si -post-1.xml), verificate la 12.11.2026. */
    public const WORDPRESS_URLS = ['/', '/abonamente', '/echipa', '/hikevet-contact', '/video-vet', '/servicii', '/servicii-ortopedie', '/serviciu-de-ambulanta', '/analize-de-laborator',
        '/radiografie', '/ecografie', '/medicina-interna', '/anestezie-inhalatorie-si-analgezie', '/terapie-intensiva', '/consultatii-la-domiciliu', '/chirurgie-tesuturi-moi', '/dermatologie',
        '/nutritie', '/oftamologie', '/neurologie', '/detartraj', '/vaccinare', '/deparazitare-interna', '/deparazitare-externa', '/microcipare', '/testarea-progesteronului', '/pasaport-calatorie',
        '/citologie-vaginala', '/inseminare-artificiala', '/intrebari-frecvente', '/blog', '/tur-3d', '/termeni-si-conditii-abonamente', '/contract-abonament', '/anexa-abonamente', '/ghid-proprietar',
        '/acces-cont', '/consimtamant-tableta',
        '/primul-caine-aici-ai-cateva-sfaturi-pentru-un-caine-sanatos-si-fericit', '/un-ghid-cuprinzator-pentru-ingrijirea-pisicilor-pastrati-va-prietenul-felin-fericit-si-sanatos',
        '/beneficiile-castrarii-caineluimascul-pentru-sanatatea-lui-o-perspectiva-stiintifica', '/beneficiile-sterilizari-cainelui-dvs-pentru-sanatatea-acestuia-o-perspectiva-stiintifica'];

    public function test_every_public_wordpress_url_still_answers(): void
    {
        foreach (self::WORDPRESS_URLS as $url) {
            $response = $this->get($url);
            $this->assertSame(200, $response->status(), $url);
            $this->assertSame(1, substr_count($response->getContent(), '<h1'), "Un singur h1 pe $url");
        }
    }

    public function test_retired_wordpress_urls_redirect_permanently(): void
    {
        foreach (['/shop' => '/', '/cart' => '/', '/checkout' => '/', '/my-account' => '/acces-cont/', '/thank-you-page' => '/acces-cont/', '/inregistrare-cont' => '/acces-cont/',
            '/asigurari' => '/abonamente/', '/category/uncategorized' => '/blog/', '/feed' => '/blog/'] as $from => $to) {
            $this->get($from)->assertStatus(301)->assertRedirect('http://localhost:8000'.$to);
        }
        $this->get('/?page_id=2903')->assertStatus(301)->assertRedirect('http://localhost:8000/servicii/');
        $this->get('/panou-cont')->assertRedirect('http://localhost:8000/acces-cont/');
        $this->get('/pagina-care-nu-exista')->assertNotFound();
        $this->assertDatabaseHas('redirects', ['from_path' => '/asigurari/', 'hits' => 1]);
    }

    public function test_content_pages_get_a_canonical_trailing_slash(): void
    {
        // Clientul HTTP din teste elimina slash-ul final, deci regula este verificata direct pe middleware, in afara mediului de test.
        $this->app->detectEnvironment(fn () => 'local');
        $middleware = new PublicRedirects;
        $next = fn () => response('ok');

        $response = $middleware->handle(Request::create('/servicii', 'GET', ['utm' => 'x']), $next);
        $this->assertSame(301, $response->getStatusCode());
        $this->assertSame('http://localhost/servicii/?utm=x', $response->headers->get('Location'));

        $this->assertSame(200, $middleware->handle(Request::create('/servicii/'), $next)->getStatusCode());
        $this->assertSame(200, $middleware->handle(Request::create('/sitemap.xml'), $next)->getStatusCode());
        $this->assertSame(200, $middleware->handle(Request::create('/panou-cont/contracte/1/pdf'), $next)->getStatusCode());
        $this->assertSame(200, $middleware->handle(Request::create('/hikevet-contact', 'POST'), $next)->getStatusCode());
        $this->app->detectEnvironment(fn () => 'testing');
    }

    public function test_home_shows_real_content_and_never_calls_the_subscription_insurance(): void
    {
        $response = $this->get('/');

        $response->assertSee('Clinică veterinară')->assertSee('Bine ai venit la HiKeVet')->assertSee('Dr. Radu Feșteu')->assertSee('Radiografie')
            ->assertSee('+40 771 280 345')->assertSee('Str. Băneasa nr. 23')->assertSee('https://vet.digitail.io/clinics/clinica-veterinara-hikevet', false);
        foreach (['/', '/abonamente', '/servicii', '/radiografie', '/video-vet', '/hikevet-contact'] as $url) {
            $text = mb_strtolower(strip_tags($this->get($url)->getContent()));
            $this->assertStringNotContainsString('plan de asigurare', $text, $url);
            $this->assertStringNotContainsString('asigurare medical', $text, $url);
        }
        $this->get('/abonamente')->assertSee('nu o asigurare')->assertSee('Deparazitările interne și externe nu sunt incluse');
    }

    public function test_seo_tags_structured_data_sitemap_and_robots(): void
    {
        $home = $this->get('/');
        $home->assertSee('<link rel="canonical" href="http://localhost:8000/">', false)->assertSee('"@type":"VeterinaryCare"', false)->assertSee('og:title', false)->assertSee('<html lang="ro">', false);
        $this->assertStringNotContainsString('openingHours', $home->getContent(), 'Programul nu apare in datele structurate pana nu este confirmat.');
        $this->assertStringNotContainsString('aggregateRating', $home->getContent());

        $this->get('/radiografie')->assertSee('<link rel="canonical" href="http://localhost:8000/radiografie/">', false)->assertSee('"@type":"BreadcrumbList"', false)->assertSee('"@type":"MedicalProcedure"', false);
        $this->get('/intrebari-frecvente')->assertSee('"@type":"FAQPage"', false);
        $this->get('/primul-caine-aici-ai-cateva-sfaturi-pentru-un-caine-sanatos-si-fericit')->assertSee('"@type":"Article"', false)->assertSee('og:type" content="article"', false);
        $this->get('/acces-cont')->assertSee('noindex', false);

        $sitemap = $this->get('/sitemap.xml')->assertOk()->assertHeader('content-type', 'application/xml; charset=UTF-8')->getContent();
        $this->assertSame(Page::published()->where('noindex', false)->count() + Service::published()->count() + Post::published()->count(), substr_count($sitemap, '<loc>'));
        $this->assertStringContainsString('<loc>http://localhost:8000/radiografie/</loc>', $sitemap);
        $this->assertStringNotContainsString('politica-de-confidentialitate', $sitemap, 'Pagina in lucru nu se trimite la indexare.');

        // In afara productiei site-ul nu se lasa indexat.
        $this->get('/robots.txt')->assertOk()->assertSee('Disallow: /')->assertSee('Sitemap: http://localhost:8000/sitemap.xml');
    }

    public function test_draft_pages_are_hidden_from_visitors_but_previewable_by_editors(): void
    {
        $this->get('/politica-de-confidentialitate')->assertNotFound();
        $this->actingAs($this->staff(Role::Editor))->get('/politica-de-confidentialitate')->assertOk()->assertSee('Previzualizare')->assertSee('noindex', false);
    }

    public function test_contact_form_validates_stores_notifies_and_ignores_bots(): void
    {
        $valid = ['name' => 'Vizitator Test', 'email' => 'vizitator@example.com', 'message' => 'Aș dori o programare pentru vaccinare.', 'consent' => '1', 'ts' => time() - 30];

        $this->post('/hikevet-contact', array_merge($valid, ['consent' => '']))->assertSessionHasErrors('consent');
        $this->post('/hikevet-contact', array_merge($valid, ['email' => 'nu-e-email']))->assertSessionHasErrors('email');
        $this->assertSame(0, ContactMessage::count());

        $this->post('/hikevet-contact', $valid)->assertRedirect()->assertSessionHas('contact_sent');
        $this->assertSame('Vizitator Test', ContactMessage::query()->sole()->name);
        $this->assertDatabaseHas('email_logs', ['context' => 'contact', 'status' => 'sent']);
        $this->assertDatabaseHas('email_logs', ['context' => 'contact_confirmation', 'to_email' => 'vizitator@example.com']);

        // Campul capcana completat sau formular trimis in sub 3 secunde: acelasi raspuns, nimic salvat.
        $this->post('/hikevet-contact', array_merge($valid, ['website' => 'http://spam.example']))->assertRedirect()->assertSessionHas('contact_sent');
        $this->post('/hikevet-contact', array_merge($valid, ['ts' => time()]))->assertRedirect();
        $this->assertSame(1, ContactMessage::count());
    }

    public function test_tracking_scripts_load_only_when_configured_and_wait_for_consent(): void
    {
        $this->assertStringNotContainsString('googletagmanager', $this->get('/')->getContent());

        Settings::set('marketing.gtm_id', 'GTM-TEST123');
        $html = $this->get('/')->getContent();
        $this->assertStringContainsString("analytics_storage: 'denied'", $html);
        $this->assertStringContainsString('GTM-TEST123', $html);
        $this->assertStringNotContainsString('<script async src="https://www.googletagmanager.com', $html, 'Scriptul Google se adauga doar din JavaScript, dupa accept.');
    }

    public function test_security_headers_and_no_cache_on_personal_pages(): void
    {
        $this->get('/')->assertHeader('X-Content-Type-Options', 'nosniff')->assertHeader('X-Frame-Options', 'SAMEORIGIN')->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
        $this->assertStringContainsString('no-store', $this->get('/acces-cont')->headers->get('Cache-Control'));
        $this->assertStringContainsString('no-store', $this->actingAs($this->client())->get('/panou-cont')->assertOk()->headers->get('Cache-Control'));
    }

    /** Regula implicita Laravel din .htaccess elimina slash-ul final; aplicatia il adauga. Impreuna ar da o bucla pe Apache. */
    public function test_htaccess_keeps_trailing_slashes_and_protects_hidden_files_and_uploads(): void
    {
        $htaccess = file_get_contents(public_path('.htaccess'));
        $rules = implode("\n", array_filter(explode("\n", $htaccess), fn ($line) => ! str_starts_with(ltrim($line), '#')));

        $this->assertStringNotContainsString('%1 [L,R=301]', $rules);
        $this->assertDoesNotMatchRegularExpression('#REQUEST_URI\} \(\.\+\)/\$#', $rules);
        $this->assertStringContainsString('RewriteRule ^ index.php [L]', $rules);
        $this->assertStringContainsString('well-known', $rules);
        $this->assertStringContainsString('^uploads/', $rules);
        $this->assertStringContainsString('Options -Indexes', $rules);
    }
}
