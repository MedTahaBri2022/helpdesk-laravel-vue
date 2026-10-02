<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_creates_a_customer_and_returns_a_token(): void
    {
        $response = $this->postJson('/api/register', [
            'name' => 'Ada Lovelace',
            'email' => ' Ada@Example.com ',
            'password' => 'correct-horse',
            'password_confirmation' => 'correct-horse',
        ]);

        $response->assertCreated()
            ->assertJsonPath('user.email', 'ada@example.com')
            ->assertJsonPath('user.role', 'customer')
            ->assertJsonStructure(['token']);
        $this->assertNotSame('correct-horse', User::first()->password);
    }

    public function test_a_registration_cannot_grant_itself_a_staff_role(): void
    {
        $this->postJson('/api/register', [
            'name' => 'Mallory',
            'email' => 'mallory@example.com',
            'password' => 'correct-horse',
            'password_confirmation' => 'correct-horse',
            'role' => 'admin',
        ])->assertCreated();

        $this->assertSame(Role::Customer, User::first()->role);
    }

    public function test_registration_reports_each_invalid_field(): void
    {
        User::factory()->create(['email' => 'taken@example.com']);

        $this->postJson('/api/register', [
            'name' => 'A',
            'email' => 'taken@example.com',
            'password' => 'short',
            'password_confirmation' => 'different',
        ])->assertUnprocessable()->assertJsonValidationErrors(['name', 'email', 'password']);
    }

    public function test_login_returns_a_token_that_authenticates_requests(): void
    {
        $user = User::factory()->agent()->create(['email' => 'agent@example.com']);

        $token = $this->postJson('/api/login', ['email' => 'agent@example.com', 'password' => 'password'])
            ->assertOk()
            ->json('token');

        $this->withToken($token)->getJson('/api/me')
            ->assertOk()
            ->assertJsonPath('data.id', $user->id)
            ->assertJsonPath('data.role', 'agent')
            ->assertJsonMissingPath('data.password');
    }

    public function test_login_answers_the_same_for_a_wrong_password_and_an_unknown_email(): void
    {
        User::factory()->create(['email' => 'known@example.com']);

        $wrongPassword = $this->postJson('/api/login', ['email' => 'known@example.com', 'password' => 'nope']);
        $unknownEmail = $this->postJson('/api/login', ['email' => 'nobody@example.com', 'password' => 'nope']);

        $wrongPassword->assertUnprocessable();
        $this->assertSame($wrongPassword->json('errors'), $unknownEmail->json('errors'));
    }

    public function test_logout_revokes_the_token(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('spa')->plainTextToken;

        $this->withToken($token)->postJson('/api/logout')->assertNoContent();

        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_protected_routes_require_authentication(): void
    {
        $this->getJson('/api/tickets')->assertUnauthorized();
        $this->getJson('/api/me')->assertUnauthorized();
        $this->getJson('/api/stats')->assertUnauthorized();
    }
}
