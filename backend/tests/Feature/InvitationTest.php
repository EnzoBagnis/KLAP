<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Mail\InvitationMail;
use App\Models\Invitation;
use App\Models\KlapNotification;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

/** Invitations et activation de compte (#126, repris en partie de la branche de Lucas, #121). */
class InvitationTest extends TestCase
{
    use RefreshDatabase;

    private const TOKEN = 'jeton-de-test-0123456789-abcdefghijklmnopqrstuvwxyz-ABCDEFGHIJKL';

    private const PASSWORD = 'Danse2026!';

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->assertSame(64, strlen(self::TOKEN));
        $this->admin = User::factory()->role(UserRole::Admin)->create(['name' => 'Mariette Travard']);
    }

    private function invite(array $overrides = [])
    {
        return $this->actingAs($this->admin)->postJson('/api/invitations', [
            'full_name' => 'Paul Martin',
            'email' => 'paul.martin@example.org',
            'role' => 'contributor',
            ...$overrides,
        ]);
    }

    private function pending(array $attributes = []): Invitation
    {
        return Invitation::factory()->withToken(self::TOKEN)->create([
            'email' => 'paul.martin@example.org', 'full_name' => 'Paul Martin', 'invited_by' => $this->admin->id, ...$attributes,
        ]);
    }

    private function activate(array $body = [])
    {
        return $this->postJson('/api/invitations/'.self::TOKEN.'/activate', [
            'password' => self::PASSWORD, 'password_confirmation' => self::PASSWORD, ...$body,
        ]);
    }

    // Création

    public function test_l_administratrice_invite_et_le_mail_part_avec_le_lien(): void
    {
        Mail::fake();

        $response = $this->invite(['email' => '  Paul.Martin@Example.org '])
            ->assertCreated()
            ->assertJsonPath('data.email', 'paul.martin@example.org')
            ->assertJsonPath('data.role', 'contributor');

        $invitation = Invitation::query()->sole();
        $this->assertSame($this->admin->id, $invitation->invited_by);
        $this->assertTrue($invitation->expires_at->between(now()->addDays(7)->subMinute(), now()->addDays(7)->addMinute()));
        Mail::assertSent(InvitationMail::class, function (InvitationMail $mail) use ($invitation) {
            $token = substr($mail->url, strrpos($mail->url, '/') + 1);

            return $mail->hasTo('paul.martin@example.org')
                && str_starts_with($mail->url, 'http://localhost:3000/activation/')
                && Invitation::hashToken($token) === $invitation->token_hash;
        });
        // Le jeton n'apparaît ni dans la réponse ni en clair en base.
        $this->assertStringNotContainsString('token', $response->getContent());
    }

    public function test_le_mail_reprend_le_nom_et_l_echeance(): void
    {
        $invitation = $this->pending()->load('inviter');
        $mail = new InvitationMail($invitation, 'http://localhost:3000/activation/x');

        $mail->assertSeeInText('Bonjour Paul Martin');
        $mail->assertSeeInText('Mariette Travard vous invite');
        $mail->assertSeeInText('http://localhost:3000/activation/x');
    }

    public function test_un_echec_d_envoi_n_enregistre_pas_d_invitation(): void
    {
        Mail::shouldReceive('to')->andThrow(new RuntimeException('SMTP injoignable'));

        $this->invite()->assertStatus(503)->assertJsonPath('message', 'Le mail d’invitation n’a pas pu être envoyé. Réessayez dans un instant.');
        $this->assertSame(0, Invitation::query()->count());
    }

    public static function forbidden(): array
    {
        return ['super-utilisatrice' => [UserRole::SuperUser], 'intervenant·e' => [UserRole::Contributor]];
    }

    #[DataProvider('forbidden')]
    public function test_seule_l_administratrice_invite(UserRole $role): void
    {
        $this->actingAs(User::factory()->role($role)->create())->postJson('/api/invitations', [])->assertForbidden();
    }

    public function test_inviter_demande_une_connexion(): void
    {
        $this->postJson('/api/invitations', [])->assertUnauthorized();
    }

    public static function invalid(): array
    {
        return [
            'sans nom' => [['full_name' => ''], 'full_name'],
            'nom absent' => [['full_name' => null], 'full_name'],
            'nom trop long' => [['full_name' => str_repeat('a', 161)], 'full_name'],
            'adresse invalide' => [['email' => 'paul'], 'email'],
            'rôle administratrice' => [['role' => 'admin'], 'role'],
            'rôle inconnu' => [['role' => 'artist'], 'role'],
        ];
    }

    #[DataProvider('invalid')]
    public function test_refuse_une_invitation_invalide(array $overrides, string $field): void
    {
        Mail::fake();
        $this->invite($overrides)->assertUnprocessable()->assertJsonValidationErrors($field);
        Mail::assertNothingSent();
    }

    public function test_refuse_une_adresse_qui_a_deja_un_compte(): void
    {
        Mail::fake();
        User::factory()->create(['email' => 'paul.martin@example.org']);

        $this->invite()->assertUnprocessable()->assertJsonPath('errors.email.0', 'Un compte existe déjà avec cette adresse.');
    }

    public function test_une_nouvelle_invitation_remplace_celle_en_cours(): void
    {
        Mail::fake();
        $old = $this->pending();

        $this->invite()->assertCreated()->assertJsonPath('data.replaced', true);
        $this->getJson('/api/invitations/'.self::TOKEN)->assertStatus(410); // l'ancien lien ne sert plus
        $this->assertFalse($old->fresh()->isUsable());
        $this->assertSame(1, Invitation::query()->pending()->count());

        $this->invite(['email' => 'autre@example.org'])->assertJsonPath('data.replaced', false);
    }

    public function test_refuse_un_compte_existant_quelle_que_soit_la_casse(): void
    {
        Mail::fake();
        User::factory()->create(['email' => 'Paul.Martin@Example.org']);

        $this->invite()->assertUnprocessable()->assertJsonValidationErrors('email');
    }

    public function test_refuse_une_adresse_trop_longue_pour_se_connecter(): void
    {
        Mail::fake();
        $this->invite(['email' => str_repeat('a', 180).'@example.org'])
            ->assertUnprocessable()
            ->assertJsonPath('errors.email.0', 'L’adresse mail ne doit pas dépasser 190 caractères.');
    }

    public function test_une_invitation_de_super_utilisatrice_donne_ce_role(): void
    {
        $this->pending(['role' => UserRole::SuperUser]);
        $this->activate()->assertCreated();

        $this->assertSame(UserRole::SuperUser, User::query()->where('email', 'paul.martin@example.org')->sole()->role);
    }

    public function test_seule_l_administratrice_qui_a_invite_est_prevenue(): void
    {
        $other = User::factory()->role(UserRole::Admin)->create();
        $this->pending();
        $this->activate()->assertCreated();

        $this->assertSame(1, KlapNotification::query()->where('recipient_id', $this->admin->id)->count());
        $this->assertSame(0, KlapNotification::query()->where('recipient_id', $other->id)->count());
    }

    public function test_un_mot_de_passe_absent_est_refuse(): void
    {
        $this->pending();
        $this->postJson('/api/invitations/'.self::TOKEN.'/activate', [])
            ->assertUnprocessable()
            ->assertJsonPath('errors.password.0', 'Choisissez un mot de passe.');
    }

    public function test_les_liens_n_epuisent_pas_les_tentatives_de_connexion(): void
    {
        foreach (range(1, 9) as $attempt) {
            $this->getJson('/api/invitations/'.str_repeat('z', 64))->assertNotFound();
        }
        // Les 5 tentatives de connexion restent disponibles (identifiants faux : 422, pas 429).
        foreach (range(1, 5) as $attempt) {
            $this->postJson('/api/login', ['email' => 'x@example.org', 'password' => 'faux'])->assertUnprocessable();
        }
    }

    // Lecture publique

    public function test_lit_une_invitation_valide_sans_connexion(): void
    {
        $this->pending(['role' => UserRole::SuperUser]);

        $this->getJson('/api/invitations/'.self::TOKEN)
            ->assertOk()
            ->assertJsonPath('data.full_name', 'Paul Martin')
            ->assertJsonPath('data.email', 'paul.martin@example.org')
            ->assertJsonPath('data.role', 'super_user');
    }

    public function test_jeton_inconnu_expire_ou_deja_utilise(): void
    {
        $this->getJson('/api/invitations/'.str_repeat('x', 64))->assertNotFound();
        $this->getJson('/api/invitations/court')->assertNotFound();

        $invitation = $this->pending(['expires_at' => now()->subMinute()]);
        $this->getJson('/api/invitations/'.self::TOKEN)->assertStatus(410)->assertJsonPath('message', 'Ce lien a expiré. Demandez une nouvelle invitation à l’administratrice.');

        $invitation->forceFill(['expires_at' => now()->addDay(), 'accepted_at' => now()])->save();
        $this->getJson('/api/invitations/'.self::TOKEN)->assertStatus(410);
    }

    // Activation

    public function test_active_le_compte_avec_le_nom_et_l_adresse_de_l_invitation(): void
    {
        $invitation = $this->pending();

        $this->activate(['name' => 'Autre nom', 'email' => 'pirate@example.org'])
            ->assertCreated()
            ->assertJsonPath('data.email', 'paul.martin@example.org')
            ->assertJsonPath('data.name', 'Paul Martin');

        $user = User::query()->where('email', 'paul.martin@example.org')->sole();
        $this->assertSame('Paul Martin', $user->name);
        $this->assertSame(UserRole::Contributor, $user->role);
        $this->assertTrue($user->is_active);
        $this->assertFalse(User::query()->where('email', 'pirate@example.org')->exists());
        $this->assertSame($user->id, $invitation->fresh()->user_id);
        $this->assertNotNull($invitation->fresh()->accepted_at);

        $notification = KlapNotification::query()->where('recipient_id', $this->admin->id)->sole();
        $this->assertSame('invitation_accepted', $notification->type);
        $this->assertSame('Paul Martin a activé son compte.', $notification->message);

        // Le compte se connecte ensuite normalement.
        $this->app['auth']->forgetGuards();
        $this->postJson('/api/login', ['email' => 'paul.martin@example.org', 'password' => self::PASSWORD])->assertOk();
    }

    public function test_une_seconde_activation_est_refusee(): void
    {
        $this->pending();
        $this->activate()->assertCreated();

        $this->activate()->assertStatus(410);
        $this->assertSame(1, User::query()->where('email', 'paul.martin@example.org')->count());
    }

    public function test_n_active_pas_une_invitation_expiree_ou_inconnue(): void
    {
        $this->pending(['expires_at' => now()->subMinute()]);
        $this->activate()->assertStatus(410);

        $this->postJson('/api/invitations/'.str_repeat('y', 64).'/activate', [
            'password' => self::PASSWORD, 'password_confirmation' => self::PASSWORD,
        ])->assertNotFound();
        $this->assertSame(0, User::query()->where('email', 'paul.martin@example.org')->count());
    }

    public static function weakPasswords(): array
    {
        return [
            'trop court' => ['Da2026!', 'au moins 8 caractères'],
            'sans majuscule' => ['danse2026!', 'majuscule'],
            'sans chiffre' => ['DanseDanse!', 'chiffre'],
            'sans caractère spécial' => ['Danse2026', 'caractère spécial'],
        ];
    }

    #[DataProvider('weakPasswords')]
    public function test_refuse_un_mot_de_passe_trop_faible(string $password, string $expected): void
    {
        $this->pending();

        $response = $this->activate(['password' => $password, 'password_confirmation' => $password])->assertUnprocessable();
        $this->assertStringContainsString($expected, implode(' ', $response->json('errors.password')));
    }

    public function test_refuse_une_confirmation_differente(): void
    {
        $this->pending();

        $this->activate(['password_confirmation' => 'Autre2026!'])
            ->assertUnprocessable()
            ->assertJsonPath('errors.password.0', 'La confirmation ne correspond pas au mot de passe.');
    }

    public function test_adresse_prise_entre_temps(): void
    {
        $this->pending();
        User::factory()->create(['email' => 'paul.martin@example.org']);

        $this->activate()->assertStatus(409);
        $this->assertNull(Invitation::query()->sole()->accepted_at);
        $this->assertSame(0, KlapNotification::query()->count());
    }

    public function test_limite_les_tentatives_sur_les_liens(): void
    {
        foreach (range(1, 10) as $attempt) {
            $this->getJson('/api/invitations/'.str_repeat('z', 64))->assertNotFound();
        }
        $this->getJson('/api/invitations/'.str_repeat('z', 64))->assertTooManyRequests();
    }

    public function test_le_jeton_n_est_stocke_que_sous_forme_d_empreinte(): void
    {
        Mail::fake();
        $this->invite()->assertCreated();
        $sent = null;
        Mail::assertSent(InvitationMail::class, function (InvitationMail $mail) use (&$sent) {
            $sent = substr($mail->url, strrpos($mail->url, '/') + 1);

            return true;
        });

        $row = (array) DB::table('invitations')->first();
        $this->assertNotContains($sent, $row);
        $this->assertSame(hash('sha256', $sent), $row['token_hash']);
    }
}
