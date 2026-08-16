<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    /**
     * Cria/atualiza o usuário administrador único do sistema.
     *
     * As credenciais vêm do .env (ADMIN_USERNAME / ADMIN_PASSWORD) para não
     * ficarem hardcoded no repositório. Se a senha não for definida, uma
     * aleatória é gerada e impressa uma única vez no console.
     */
    public function run(): void
    {
        $username = env('ADMIN_USERNAME', 'admin');
        $password = env('ADMIN_PASSWORD');
        $email = env('ADMIN_EMAIL'); // opcional

        $generated = false;
        if (blank($password)) {
            $password = Str::password(16);
            $generated = true;
        }

        $user = User::updateOrCreate(
            ['username' => $username],
            [
                'name' => env('ADMIN_NAME', $username),
                'email' => $email,
                // cast 'hashed' no model User faz o hash automaticamente
                'password' => $password,
            ],
        );

        $this->command->info("Admin pronto: usuário '{$user->username}'.");
        if ($generated) {
            $this->command->warn("Senha gerada (anote, não será exibida de novo): {$password}");
        }
    }
}
