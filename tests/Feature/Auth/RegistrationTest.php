<?php

namespace Tests\Feature\Auth;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Fortify\Features;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->skipUnlessFortifyHas(Features::registration());
    }

    public function test_registration_screen_can_be_rendered(): void
    {
        $response = $this->get(route('register'));

        $response->assertOk();
    }

    public function test_new_users_can_register(): void
    {
        $response = $this->post(route('register.store'), [
            'name' => 'John Doe',
            'company_name' => 'Doe Studio',
            'email' => 'test@example.com',
            'password' => 'secreto#123',
            'password_confirmation' => 'secreto#123',
        ]);

        $response->assertSessionHasNoErrors()
            ->assertRedirect(route('dashboard', absolute: false));

        $this->assertAuthenticated();

        $user = User::firstWhere('email', 'test@example.com');
        $this->assertSame('Doe Studio', $user->company_name);
        $this->assertSame(UserRole::Photographer, $user->role);
    }

    public function test_company_name_is_optional(): void
    {
        $response = $this->post(route('register.store'), [
            'name' => 'John Doe',
            'email' => 'test@example.com',
            'password' => 'secreto#123',
            'password_confirmation' => 'secreto#123',
        ]);

        $response->assertSessionHasNoErrors();
        $this->assertAuthenticated();
        $this->assertNull(User::firstWhere('email', 'test@example.com')->company_name);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function weakPasswords(): array
    {
        return [
            'too short' => ['ab#1'],
            'no number' => ['secreto#abc'],
            'no special character' => ['secreto1234'],
        ];
    }

    #[DataProvider('weakPasswords')]
    public function test_weak_passwords_are_rejected(string $password): void
    {
        $response = $this->post(route('register.store'), [
            'name' => 'John Doe',
            'company_name' => 'Doe Studio',
            'email' => 'test@example.com',
            'password' => $password,
            'password_confirmation' => $password,
        ]);

        $response->assertSessionHasErrors('password');
        $this->assertGuest();
    }

    public function test_registration_cannot_self_assign_the_superadmin_role(): void
    {
        $this->post(route('register.store'), [
            'name' => 'Intruder',
            'company_name' => 'Intruder Inc',
            'email' => 'intruder@example.com',
            'password' => 'secreto#123',
            'password_confirmation' => 'secreto#123',
            'role' => 'superadmin',
        ]);

        $this->assertSame(UserRole::Photographer, User::firstWhere('email', 'intruder@example.com')->role);
    }
}
