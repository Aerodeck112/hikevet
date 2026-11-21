<?php

namespace Tests\Feature;

use App\Mail\GenericNotification;
use App\Support\Buddies;
use Database\Seeders\SiteContentSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Pisica si cainele de pe fatada apar pe tot site-ul: antete, footer, erori, cont, emailuri, admin. */
class BuddiesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedBusiness();
        $this->seed(SiteContentSeeder::class);
    }

    public function test_each_section_gets_its_own_character_and_lines(): void
    {
        $this->assertSame('dog', Buddies::forPath('servicii')['kind']);
        $this->assertSame('dog', Buddies::forPath('servicii/vaccinare')['kind']);
        $this->assertSame('cat', Buddies::forPath('echipa')['kind']);
        $this->assertSame('sleep', Buddies::forPath('termeni-si-conditii-abonamente')['pose']);
        $this->assertSame('sleep', Buddies::forPath('contract-abonament')['pose']);
        $this->assertStringContainsString('Se întâmplă', Buddies::forPath('acces-cont/parola-uitata')['say'][0]);
        $this->assertStringContainsString('pisica de pază', Buddies::forPath('acces-cont')['say'][0]);
        // Paginile fara intrare primesc mereu acelasi personaj
        $this->assertSame(Buddies::forPath('o-pagina-noua'), Buddies::forPath('o-pagina-noua'));
        $this->assertNotEmpty(Buddies::forPath('o-pagina-noua')['say']);
    }

    public function test_inner_pages_show_the_character_in_the_header_and_the_sleeping_cat_in_the_footer(): void
    {
        foreach (['/servicii/', '/echipa/', '/blog/', '/hikevet-contact/', '/termeni-si-conditii-abonamente/', '/acces-cont/'] as $url) {
            $html = $this->get($url)->assertOk()->getContent();
            $this->assertStringContainsString('page-head--buddy', $html, $url);
            $this->assertStringContainsString('head-buddy', $html, $url);
            $this->assertStringContainsString('footer-cat', $html, $url);
            $this->assertStringContainsString('data-say=', $html, $url);
        }
        $this->get('/servicii/')->assertSee('Eu vin pentru vaccin. Tu?', false);
        // Personajele au nume: Raza (pisica) si Hiko (cainele)
        $this->get('/servicii/')->assertSee('<b class="buddy-name">Hiko</b>', false)->assertSee('Hiko, câinele HiKeVet: apasă și îți spune ceva', false);
        $this->get('/echipa/')->assertSee('<b class="buddy-name">Raza</b>', false);
        $this->get('/')->assertSee('Hiko, câinele HiKeVet: apasă și latră', false);
        $this->get('/')->assertOk()->assertSee('footer-cat', false);
    }

    public function test_missing_pages_get_the_searching_dog(): void
    {
        $this->get('/pagina-care-nu-exista-deloc/')
            ->assertNotFound()
            ->assertSee('Pagina asta nu e aici')
            ->assertSee('buddy--dog', false)
            ->assertSee('Mergi la prima pagină')
            ->assertSee('noindex', false);
    }

    public function test_maintenance_and_expired_pages_render_with_the_cat(): void
    {
        $this->assertStringContainsString('Facem puțină curățenie', view('errors.503')->render());
        $this->assertStringContainsString('buddy--sleep', view('errors.419')->render());
        $this->assertStringContainsString('buddy--cat', view('errors.500')->render());
        $this->assertStringContainsString('Prea multe încercări', view('errors.429')->render());
    }

    public function test_client_dashboard_greets_by_first_name_and_fills_empty_states(): void
    {
        $client = $this->client();
        $client->forceFill(['name' => 'Ioana Pop'])->save();
        $html = $this->actingAs($client)->get('/panou-cont/')->assertOk()->getContent();
        // Replicile stau in data-say ca JSON (codificat sigur pentru atribut)
        $this->assertStringContainsString(trim(json_encode('Ce bine că ai venit, Ioana!', JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT), '"'), $html);
        $this->assertSame(2, substr_count($html, 'empty-buddy'));
    }

    public function test_emails_and_staff_login_carry_the_characters(): void
    {
        $this->assertFileExists(public_path('media/personaje/pisica-si-caine.png'));
        $html = (new GenericNotification('Test', '<p>Salut</p>'))->render();
        $this->assertStringContainsString('media/personaje/pisica-si-caine.png', $html);

        $this->get('/admin/login')->assertOk()->assertSee('Bună tura de azi', false);
    }

    public function test_footer_credits_vitim_with_a_link_to_vitim_ro(): void
    {
        $this->get('/')->assertOk()
            ->assertSee('href="https://vitim.ro/?utm_source=hikevet.ro&amp;utm_medium=referral&amp;utm_campaign=footer"', false)
            ->assertSee('<strong>VITIM</strong>', false)
            ->assertSee('Site dezvoltat de VITIM', false);
    }
}
