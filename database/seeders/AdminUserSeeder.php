<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        $email = env('ADMIN_EMAIL', 'admin@example.com');
        if (User::query()->where('email', $email)->exists()) {
            return;
        }

        $password = env('ADMIN_PASSWORD') ?: Str::password(16, symbols: false);

        User::query()->create([
            'name' => env('ADMIN_NAME', 'Pürüz Yönetici'),
            'email' => $email,
            'password' => $password,
            'role' => UserRole::Admin,
        ]);

        if (! env('ADMIN_PASSWORD')) {
            $this->command?->warn("Yönetici hesabı oluşturuldu: {$email} / {$password} (giriş yaptıktan sonra şifreyi değiştirin)");
        }
    }
}
