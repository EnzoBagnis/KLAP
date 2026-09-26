<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

// Mot de passe de tous les comptes : Klap2026!
// A part Mariette et Emilie, les personnes et les mails en @klap.demo sont inventés
class UserSeeder extends Seeder
{
    public function run(): void
    {
        $pwd = Hash::make('Klap2026!');

        $users = [
            [1, 'Mariette Travard', 'mariette.travard@klap.demo', 'admin',       true,  '2026-09-21 09:12:41', '2026-08-25 10:03:12'],
            [2, 'Emilie Garetier',  'emilie.garetier@klap.demo',  'super_user',  true,  '2026-09-19 17:38:02', '2026-08-25 10:06:47'],
            [3, 'Léa Fournier',     'lea.fournier@klap.demo',     'contributor', true,  '2026-09-18 12:26:15', '2026-08-28 14:11:30'],
            [4, 'Hugo Martin',      'hugo.martin@klap.demo',      'contributor', true,  null,                  '2026-08-28 14:12:05'],
            [5, 'Nina Roussel',     'nina.roussel@klap.demo',     'contributor', true,  '2026-09-15 12:51:33', '2026-08-29 09:34:20'],
            [6, 'Samir Benali',     'samir.benali@klap.demo',     'contributor', true,  '2026-09-19 19:02:58', '2026-09-02 11:00:09'],
            [7, 'Claire Dubois',    'claire.dubois@klap.demo',    'contributor', false, '2026-06-30 18:00:00', '2025-09-10 10:00:00'],
        ];

        foreach ($users as [$id, $name, $email, $role, $active, $lastLogin, $createdAt]) {
            DB::table('users')->insert([
                'id' => $id,
                'name' => $name,
                'email' => $email,
                'password' => $pwd,
                'role' => $role,
                'is_active' => $active,
                'last_login_at' => $lastLogin,
                'created_at' => $createdAt,
                'updated_at' => $createdAt,
            ]);
        }

        // Samir a accepté son invitation, celle d'Inès est encore en attente
        DB::table('invitations')->insert([
            [
                'email' => 'samir.benali@klap.demo', 'full_name' => 'Samir Benali', 'role' => 'contributor',
                'token_hash' => hash('sha256', 'demo-token-samir'), 'invited_by' => 1, 'user_id' => 6,
                'sent_at' => '2026-09-01 15:00:00', 'expires_at' => '2026-09-08 15:00:00', 'accepted_at' => '2026-09-02 11:00:00',
            ],
            [
                'email' => 'ines.morel@klap.demo', 'full_name' => 'Inès Morel', 'role' => 'contributor',
                'token_hash' => hash('sha256', 'demo-token-ines'), 'invited_by' => 1, 'user_id' => null,
                'sent_at' => '2026-09-20 10:30:00', 'expires_at' => '2026-09-27 10:30:00', 'accepted_at' => null,
            ],
        ]);
    }
}
