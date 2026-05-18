<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use App\Models\User;
use App\Models\Organization;
use App\Models\Facility;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // 1. Buat Data Fasilitas
        Facility::insert([
            ['name' => 'Lapangan Basket', 'type' => 'Outdoor', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Aula Utama', 'type' => 'Indoor', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Ruang Rapat MPK', 'type' => 'Indoor', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Ruang Teori', 'type' => 'Indoor', 'created_at' => now(), 'updated_at' => now()],
        ]);

        // 2. Buat Data Organisasi/Eskul
        $basket = Organization::create([
            'name' => 'Eskul Basket',
            'advisor_name' => 'Pak Budi'
        ]);

        $mpk = Organization::create([
            'name' => 'MPK / OSIS',
            'advisor_name' => 'Bu Siti'
        ]);

        // 3. Buat Data Users dengan Role Berbeda
        
        // Akun Admin / MPK
        User::create([
            'name' => 'Admin MPK',
            'email' => 'admin@mpk.com',
            'password' => Hash::make('password'),
            'year' => '2024',
            'major' => 'RPL',
            'class' => 'A',
            'role' => 'admin_mpk',
        ]);

        // Akun Pemilik Eskul (Contoh: Basket)
        User::create([
            'name' => 'Ketua Basket',
            'email' => 'ketua@basket.com',
            'password' => Hash::make('password'),
            'year' => '2024',
            'major' => 'TKJ',
            'class' => 'A',
            'role' => 'owner_eskul',
        ]);

        // Akun User / Peminjam Biasa
        User::create([
            'name' => 'Siswa Peminjam',
            'email' => 'siswa@sekolah.com',
            'password' => Hash::make('password'),
            'year' => '2024',
            'major' => 'RPL',
            'class' => 'B',
            'role' => 'user',
        ]);
    }
}