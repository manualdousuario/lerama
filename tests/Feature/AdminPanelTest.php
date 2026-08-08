<?php

namespace Tests\Feature;

use App\Models\User;
use Filament\Auth\Pages\Login;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;

it('redirects a guest from admin to login', function () {
    $this->get('/admin')->assertRedirect('/admin/login');
    $this->get('/admin/feeds')->assertRedirect('/admin/login');
    $this->get('/admin/categories')->assertRedirect('/admin/login');
});

it('reaches the login page', function () {
    $this->get('/admin/login')->assertOk();
});

it('logs in with valid credentials', function () {
    adminPanelCreateAdmin();

    Livewire::test(Login::class)
        ->fillForm([
            'email' => 'admin@lerama.local',
            'password' => 'strong-password-123',
        ])
        ->call('authenticate')
        ->assertHasNoFormErrors();

    $this->assertAuthenticated();
});

it('rejects a login with the wrong password', function () {
    adminPanelCreateAdmin();

    Livewire::test(Login::class)
        ->fillForm([
            'email' => 'admin@lerama.local',
            'password' => 'errada',
        ])
        ->call('authenticate')
        ->assertHasFormErrors(['email']);

    $this->assertGuest();
});

it('renders the panel pages for an authenticated admin', function () {
    [$feed] = $this->seedBasicData();
    $this->actingAs(adminPanelCreateAdmin());

    // The panel root has no dashboard; it lands on the first resource.
    $this->get('/admin')->assertRedirect('/admin/feed-items');

    $this->get('/admin/feed-items')->assertOk();
    $this->get('/admin/feeds')->assertOk();
    $this->get('/admin/feeds/create')->assertOk();
    $this->get("/admin/feeds/{$feed->id}/edit")->assertOk();
    $this->get('/admin/categories')->assertOk();
    $this->get('/admin/tags')->assertOk();
});

it('logs out', function () {
    $this->actingAs(adminPanelCreateAdmin());

    $this->post('/admin/logout')->assertRedirect('/admin/login');
    $this->assertGuest();
});

it('creates and updates the operator with the setup admin command', function () {
    config([
        'lerama.admin.username' => 'operador',
        'lerama.admin.password' => 'senha-bem-forte',
        'lerama.admin.email' => 'operador@lerama.local',
    ]);

    $this->artisan('lerama:setup-admin')->assertSuccessful();

    $user = User::where('email', 'operador@lerama.local')->firstOrFail();

    expect($user->name)->toBe('operador')
        ->and(Hash::check('senha-bem-forte', $user->password))->toBeTrue();

    config(['lerama.admin.password' => 'outra-senha-forte']);
    $this->artisan('lerama:setup-admin')->assertSuccessful();

    expect(User::where('email', 'operador@lerama.local')->count())->toBe(1)
        ->and(Hash::check('outra-senha-forte', $user->fresh()->password))->toBeTrue();
});

it('rejects a weak password on the setup admin command', function () {
    config([
        'lerama.admin.username' => 'operador',
        'lerama.admin.password' => 'curta',
        'lerama.admin.email' => 'operador@lerama.local',
    ]);

    $this->artisan('lerama:setup-admin')->assertFailed();

    expect(User::where('email', 'operador@lerama.local')->count())->toBe(0);
});

function adminPanelCreateAdmin(): User
{
    return User::create([
        'name' => 'admin',
        'email' => 'admin@lerama.local',
        'password' => Hash::make('strong-password-123'),
    ]);
}
