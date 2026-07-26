<?php

namespace Tests\Feature;

use App\Models\User;
use Filament\Auth\Pages\Login;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

class AdminPanelTest extends TestCase
{
    private function createAdmin(): User
    {
        return User::create([
            'name' => 'admin',
            'email' => 'admin@lerama.local',
            'password' => Hash::make('strong-password-123'),
        ]);
    }

    public function test_admin_redirects_guest_to_login(): void
    {
        $this->get('/admin')->assertRedirect('/admin/login');
        $this->get('/admin/feeds')->assertRedirect('/admin/login');
        $this->get('/admin/categories')->assertRedirect('/admin/login');
    }

    public function test_login_page_is_reachable(): void
    {
        $this->get('/admin/login')->assertOk();
    }

    public function test_login_with_valid_credentials(): void
    {
        $this->createAdmin();

        Livewire::test(Login::class)
            ->fillForm([
                'email' => 'admin@lerama.local',
                'password' => 'strong-password-123',
            ])
            ->call('authenticate')
            ->assertHasNoFormErrors();

        $this->assertAuthenticated();
    }

    public function test_login_with_wrong_password(): void
    {
        $this->createAdmin();

        Livewire::test(Login::class)
            ->fillForm([
                'email' => 'admin@lerama.local',
                'password' => 'errada',
            ])
            ->call('authenticate')
            ->assertHasFormErrors(['email']);

        $this->assertGuest();
    }

    public function test_panel_pages_render_for_an_authenticated_admin(): void
    {
        [$feed] = $this->seedBasicData();
        $this->actingAs($this->createAdmin());

        // The panel root has no dashboard; it lands on the first resource.
        $this->get('/admin')->assertRedirect('/admin/feed-items');

        $this->get('/admin/feed-items')->assertOk();
        $this->get('/admin/feeds')->assertOk();
        $this->get('/admin/feeds/create')->assertOk();
        $this->get("/admin/feeds/{$feed->id}/edit")->assertOk();
        $this->get('/admin/categories')->assertOk();
        $this->get('/admin/tags')->assertOk();
    }

    public function test_logout(): void
    {
        $this->actingAs($this->createAdmin());

        $this->post('/admin/logout')->assertRedirect('/admin/login');
        $this->assertGuest();
    }

    public function test_setup_admin_command_creates_and_updates_the_operator(): void
    {
        config([
            'lerama.admin.username' => 'operador',
            'lerama.admin.password' => 'senha-bem-forte',
            'lerama.admin.email' => 'operador@lerama.local',
        ]);

        $this->artisan('lerama:setup-admin')->assertSuccessful();

        $user = User::where('email', 'operador@lerama.local')->firstOrFail();
        $this->assertSame('operador', $user->name);
        $this->assertTrue(Hash::check('senha-bem-forte', $user->password));

        // Re-running rotates the password instead of failing on the unique email.
        config(['lerama.admin.password' => 'outra-senha-forte']);
        $this->artisan('lerama:setup-admin')->assertSuccessful();

        $this->assertSame(1, User::where('email', 'operador@lerama.local')->count());
        $this->assertTrue(Hash::check('outra-senha-forte', $user->fresh()->password));
    }

    public function test_setup_admin_command_rejects_a_weak_password(): void
    {
        config([
            'lerama.admin.username' => 'operador',
            'lerama.admin.password' => 'curta',
            'lerama.admin.email' => 'operador@lerama.local',
        ]);

        $this->artisan('lerama:setup-admin')->assertFailed();

        $this->assertSame(0, User::where('email', 'operador@lerama.local')->count());
    }
}
