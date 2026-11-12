<?php

namespace Tests\Feature;

use App\Enums\Role;
use Database\Seeders\SiteContentSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/** Fiecare ecran din admin se incarca fara eroare si respecta rolurile. */
class AdminPanelTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedBusiness();
        $this->seed(SiteContentSeeder::class);
    }

    public static function screens(): array
    {
        return array_map(fn ($u) => [$u], [
            '/admin/services', '/admin/services/create', '/admin/services/1/edit', '/admin/team-members', '/admin/team-members/create', '/admin/team-members/1/edit',
            '/admin/pages', '/admin/pages/create', '/admin/pages/1/edit', '/admin/posts', '/admin/posts/create', '/admin/posts/1/edit',
            '/admin/faqs', '/admin/faqs/1/edit', '/admin/redirects', '/admin/redirects/create', '/admin/media', '/admin/media/create', '/admin/media/1/edit',
            '/admin/contact-messages', '/admin/clients', '/admin/clients/create',
            '/admin', '/admin/staff', '/admin/staff/create', '/admin/audit-log', '/admin/clinic-settings', '/admin/marketing-settings', '/admin/data-migration',
            '/admin/price-lists', '/admin/price-lists/1', '/admin/contracts', '/admin/sterilization-archive', '/admin/sterilization-reports', '/admin/profile',
        ]);
    }

    #[DataProvider('screens')]
    public function test_super_admin_can_open_every_screen(string $url): void
    {
        $this->actingAs($this->staff(Role::SuperAdmin))->get($url)->assertOk();
    }

    public function test_client_record_page_renders_with_contract_and_balances(): void
    {
        $this->postJson('/abonamente/contract', $this->contractData())->assertOk();
        $client = \App\Models\User::query()->where('email', 'popescu.test@example.com')->sole();
        $doctor = $this->staff(Role::Doctor);
        app(\App\Services\Subscriptions\SubscriptionActivation::class)->validatePayment($client->contracts()->sole(), 1, $doctor);

        $this->actingAs($doctor)->get('/admin/clients/'.$client->id)->assertOk()
            ->assertSee('HV-AB-')->assertSee('Rex')->assertSee('Puncte abonament')->assertSee('248');
        $this->actingAs($doctor)->get(route('admin.contracts.pdf', $client->contracts()->sole()))->assertOk()->assertHeader('content-type', 'application/pdf');
        $this->actingAs($this->staff(Role::Editor))->get('/admin/clients/'.$client->id)->assertForbidden();
        $this->actingAs($this->staff(Role::Editor))->get(route('admin.contracts.pdf', $client->contracts()->sole()))->assertForbidden();
        $this->assertDatabaseHas('audit_logs', ['action' => 'contract.viewed']);
    }

    public function test_guests_and_clients_cannot_enter_the_admin(): void
    {
        $this->get('/admin')->assertRedirect();
        $this->actingAs($this->client())->get('/admin')->assertForbidden();
    }

    public function test_editor_cannot_open_clinical_screens_and_doctor_cannot_edit_content(): void
    {
        $this->actingAs($this->staff(Role::Doctor))->get('/admin/services')->assertForbidden();
        $this->actingAs($this->staff(Role::Editor))->get('/admin/services')->assertOk();
    }
}
