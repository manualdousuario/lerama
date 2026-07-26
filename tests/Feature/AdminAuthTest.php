<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdminAuthTest extends TestCase
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
    }

    public function test_login_with_valid_credentials(): void
    {
        $this->createAdmin();

        $response = $this->post('/admin/login', [
            'username' => 'admin',
            'password' => 'strong-password-123',
        ]);

        $response->assertRedirect('/admin');
        $this->assertAuthenticated();
    }

    public function test_login_with_wrong_password(): void
    {
        $this->createAdmin();

        $response = $this->post('/admin/login', [
            'username' => 'admin',
            'password' => 'errada',
        ]);

        $response->assertSessionHasErrors('login');
        $this->assertGuest();
    }

    public function test_authenticated_admin_area(): void
    {
        [$feed] = $this->seedBasicData();
        $this->actingAs($this->createAdmin());

        $this->get('/admin')->assertOk();
        $this->get('/admin/feeds')->assertOk();
        $this->get('/admin/feeds/new')->assertOk();
        $this->get('/admin/categories')->assertOk();
        $this->get('/admin/tags')->assertOk();
        $this->get("/admin/feeds/{$feed->id}/edit")->assertOk();
    }

    public function test_logout(): void
    {
        $this->actingAs($this->createAdmin());

        $this->get('/admin/logout')->assertRedirect('/admin/login');
        $this->assertGuest();
    }

    public function test_setup_admin_command_creates_user(): void
    {
        config(['lerama.admin.username' => 'admin']);
        config(['lerama.admin.password' => 'strong-password-123']);
        config(['lerama.admin.email' => 'admin@example.com']);

        $this->artisan('lerama:setup-admin')->assertSuccessful();

        $this->assertDatabaseHas('users', ['email' => 'admin@example.com', 'name' => 'admin']);
    }

    public function test_setup_admin_command_rejects_weak_password(): void
    {
        config(['lerama.admin.password' => 'curta']);

        $this->artisan('lerama:setup-admin')->assertFailed();
        $this->assertSame(0, User::count());
    }
}
