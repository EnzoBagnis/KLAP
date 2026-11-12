<?php

namespace Tests\Feature\Auth;

use App\Enums\UserRole;
use App\Mail\AdminWelcomeMail;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Le premier compte d'administratrice, qu'on crée sur le serveur avec klap:admin (#255).
 */
class CreateAdminCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_cree_l_administratrice_et_lui_envoie_le_lien_qui_permet_de_choisir_son_mot_de_passe(): void
    {
        Mail::fake();
        config(['app.frontend_url' => 'https://klap.example.org']);

        $this->artisan('klap:admin', ['email' => '  Mariette@Kelemenis.FR ', 'nom' => 'Mariette   Travard'])
            ->expectsOutputToContain('Compte d’administratrice créé pour Mariette Travard')
            ->assertSuccessful();

        $user = User::query()->where('email', 'mariette@kelemenis.fr')->firstOrFail();
        $this->assertSame(UserRole::Admin, $user->role);
        $this->assertTrue($user->is_active);

        $url = null;
        Mail::assertSent(AdminWelcomeMail::class, function (AdminWelcomeMail $mail) use (&$url) {
            $url = $mail->url;

            return $mail->hasTo('mariette@kelemenis.fr') && str_starts_with($mail->url, 'https://klap.example.org/reinitialisation/');
        });

        // On vérifie que le lien du mail sert bien à choisir le mot de passe, puis à se connecter.
        $token = explode('?', basename($url))[0];
        $this->postJson('/api/reset-password', [
            'token' => $token, 'email' => 'mariette@kelemenis.fr', 'password' => 'Klap-Mariette-2026!', 'password_confirmation' => 'Klap-Mariette-2026!',
        ])->assertOk();
        $this->assertTrue(Hash::check('Klap-Mariette-2026!', $user->fresh()->password));
        $this->postJson('/api/login', ['email' => 'mariette@kelemenis.fr', 'password' => 'Klap-Mariette-2026!', 'device_name' => 't'])->assertOk();
    }

    public function test_relancee_elle_renvoie_un_lien_sans_creer_de_doublon(): void
    {
        Mail::fake();
        $this->artisan('klap:admin', ['email' => 'mariette@kelemenis.fr', 'nom' => 'Mariette Travard'])->assertSuccessful();
        $this->artisan('klap:admin', ['email' => 'mariette@kelemenis.fr', 'nom' => 'Mariette Travard'])
            ->expectsOutputToContain('un nouveau lien vient de lui être envoyé')
            ->assertSuccessful();

        $this->assertSame(1, User::query()->count());
        Mail::assertSent(AdminWelcomeMail::class, 2);
    }

    public function test_refuse_une_adresse_invalide_et_un_compte_existant_d_un_autre_role(): void
    {
        Mail::fake();
        $this->artisan('klap:admin', ['email' => 'pas-une-adresse', 'nom' => 'X'])->assertFailed();
        User::factory()->role(UserRole::Contributor)->create(['email' => 'lea@klap.fr']);
        $this->artisan('klap:admin', ['email' => 'lea@klap.fr', 'nom' => 'Léa'])
            ->expectsOutputToContain('avec un autre rôle')
            ->assertFailed();

        $this->assertSame(UserRole::Contributor, User::query()->where('email', 'lea@klap.fr')->first()->role);
        Mail::assertNothingSent();
    }

    public function test_refuse_un_compte_d_administratrice_desactive(): void
    {
        Mail::fake();
        User::factory()->role(UserRole::Admin)->create(['email' => 'mariette@kelemenis.fr', 'is_active' => false]);

        $this->artisan('klap:admin', ['email' => 'mariette@kelemenis.fr', 'nom' => 'Mariette Travard'])
            ->expectsOutputToContain('est désactivé')
            ->assertFailed();

        // On ne réactive pas le compte en douce, et on n'envoie aucun lien.
        $this->assertFalse(User::query()->where('email', 'mariette@kelemenis.fr')->firstOrFail()->is_active);
        Mail::assertNothingSent();
    }

    public function test_le_mail_de_bienvenue_donne_le_lien_et_la_marche_a_suivre(): void
    {
        $user = User::factory()->role(UserRole::Admin)->create(['name' => "Awa N'Diaye"]);
        $mail = new AdminWelcomeMail($user, 'https://klap.example.org/reinitialisation/abc?email=awa%40klap.fr', 60, 'https://klap.example.org/connexion');

        $this->assertSame('Votre compte d’administratrice sur le carnet de bord de KLAP', $mail->envelope()->subject);
        $text = $mail->render();
        // Le nom garde son apostrophe, et le lien n'est pas échappé : il doit rester cliquable.
        $this->assertStringContainsString("Bonjour Awa N'Diaye,", $text);
        $this->assertStringContainsString('https://klap.example.org/reinitialisation/abc?email=awa%40klap.fr', $text);
        $this->assertStringContainsString('valable 60 minutes', $text);
        $this->assertStringContainsString('https://klap.example.org/connexion', $text);
    }
}
