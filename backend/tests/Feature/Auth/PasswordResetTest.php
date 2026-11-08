<?php

namespace Tests\Feature\Auth;

use App\Enums\UserRole;
use App\Http\Controllers\Api\PasswordResetController;
use App\Mail\PasswordResetMail;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/** Mot de passe oublié (#81). */
class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    private User $lea;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        $this->lea = User::factory()->role(UserRole::Contributor)->create(['name' => 'Léa Fournier', 'email' => 'lea@klap.demo']);
    }

    /** Demande un lien et renvoie le jeton envoyé par mail. */
    private function requestToken(string $email = 'lea@klap.demo'): ?string
    {
        $this->postJson('/api/forgot-password', ['email' => $email])
            ->assertOk()->assertJsonPath('message', PasswordResetController::SENT);
        $token = null;
        Mail::assertSent(PasswordResetMail::class, function (PasswordResetMail $mail) use (&$token) {
            preg_match('#/reinitialisation/([^?]+)\?email=#', $mail->url, $m);
            $token = $m[1] ?? null;

            return true;
        });

        return $token;
    }

    private function reset(string $token, string $password = 'Danse#2026', string $email = 'lea@klap.demo')
    {
        return $this->postJson('/api/reset-password', [
            'token' => $token, 'email' => $email, 'password' => $password, 'password_confirmation' => $password,
        ]);
    }

    public function test_envoie_un_lien_vers_le_frontend(): void
    {
        $this->requestToken();

        Mail::assertSent(PasswordResetMail::class, fn (PasswordResetMail $mail) => $mail->hasTo('lea@klap.demo')
            && str_starts_with($mail->url, rtrim(config('app.frontend_url'), '/').'/reinitialisation/')
            && str_ends_with($mail->url, '?email=lea%40klap.demo')
            && $mail->minutes === 60);
    }

    public function test_meme_reponse_pour_une_adresse_inconnue_ou_un_compte_desactive(): void
    {
        $this->postJson('/api/forgot-password', ['email' => 'personne@klap.demo'])
            ->assertOk()->assertJsonPath('message', PasswordResetController::SENT);
        $this->lea->update(['is_active' => false]);
        $this->postJson('/api/forgot-password', ['email' => 'lea@klap.demo'])
            ->assertOk()->assertJsonPath('message', PasswordResetController::SENT);

        Mail::assertNothingSent();
    }

    public function test_change_le_mot_de_passe_et_ferme_les_sessions(): void
    {
        $this->lea->createToken('téléphone');
        $token = $this->requestToken();

        $this->reset($token)->assertOk()->assertJsonPath('message', 'Votre mot de passe est changé. Connectez-vous avec le nouveau.');

        $this->assertTrue(Hash::check('Danse#2026', $this->lea->fresh()->password));
        $this->assertSame(0, $this->lea->tokens()->count());
        $this->postJson('/api/login', ['email' => 'lea@klap.demo', 'password' => 'Danse#2026'])->assertOk();
    }

    public function test_le_lien_ne_sert_qu_une_fois(): void
    {
        $token = $this->requestToken();
        $this->reset($token)->assertOk();

        $this->reset($token, 'Autre#2027')->assertUnprocessable()
            ->assertJsonPath('errors.token.0', 'Ce lien n’est plus valable : demandez-en un nouveau.');
    }

    public function test_le_lien_expire_au_bout_d_une_heure(): void
    {
        $token = $this->requestToken();
        $this->travel(61)->minutes();

        $this->reset($token)->assertUnprocessable()->assertJsonValidationErrors('token');
        $this->assertFalse(Hash::check('Danse#2026', $this->lea->fresh()->password));
    }

    public function test_refuse_un_lien_invente_ou_une_autre_adresse(): void
    {
        $token = $this->requestToken();
        $other = User::factory()->role(UserRole::Contributor)->create(['email' => 'hugo@klap.demo']);

        $this->reset('nimportequoi')->assertJsonValidationErrors('token');
        $this->reset($token, 'Danse#2026', 'hugo@klap.demo')->assertJsonValidationErrors('token');
        $this->assertFalse(Hash::check('Danse#2026', $other->fresh()->password));
    }

    public function test_un_compte_desactive_entre_temps_ne_change_pas_de_mot_de_passe(): void
    {
        $token = $this->requestToken();
        $this->lea->update(['is_active' => false]);

        $this->reset($token)->assertJsonValidationErrors('token');
    }

    public function test_impose_un_mot_de_passe_fort(): void
    {
        $token = $this->requestToken();

        $this->reset($token, 'faible')->assertUnprocessable()
            ->assertJsonPath('errors.password.0', 'Le mot de passe doit comporter au moins 8 caractères.');
        $this->postJson('/api/reset-password', ['token' => $token, 'email' => 'lea@klap.demo', 'password' => 'Danse#2026', 'password_confirmation' => 'Autre#2026'])
            ->assertJsonPath('errors.password.0', 'La confirmation ne correspond pas au mot de passe.');
    }

    public function test_le_mail_garde_les_apostrophes_du_nom(): void
    {
        $this->lea->update(['name' => "Awa N'Diaye"]);

        $text = (new PasswordResetMail($this->lea->fresh(), 'http://site/reinitialisation/x', 60))->render();

        $this->assertStringContainsString("Bonjour Awa N'Diaye,", $text);
    }

    public function test_adresse_obligatoire_et_valide(): void
    {
        $this->postJson('/api/forgot-password', [])->assertJsonPath('errors.email.0', 'Indiquez votre adresse mail.');
        $this->postJson('/api/forgot-password', ['email' => 'pas-une-adresse'])->assertJsonPath('errors.email.0', 'L’adresse mail n’est pas valide.');
    }

    public function test_une_seule_demande_par_minute_et_par_compte(): void
    {
        $this->requestToken();
        $this->postJson('/api/forgot-password', ['email' => 'lea@klap.demo'])->assertOk();

        Mail::assertSentCount(1);
    }

    public function test_les_demandes_sont_limitees_par_adresse_ip(): void
    {
        foreach (range(1, 5) as $i) {
            $this->postJson('/api/forgot-password', ['email' => "inconnu{$i}@klap.demo"])->assertOk();
        }

        $this->postJson('/api/forgot-password', ['email' => 'lea@klap.demo'])->assertTooManyRequests();
    }
}
