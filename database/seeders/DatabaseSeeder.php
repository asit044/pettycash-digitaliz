<?php

namespace Database\Seeders;

use App\Models\BudgetCode;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        User::factory()->create([
            'name' => 'Admin Digitaliz',
            'email' => 'admin@digitaliz.id',
            'role' => 'admin',
            'phone' => '6281234567801',
        ]);

        User::factory()->create([
            'name' => 'Finance Digitaliz',
            'email' => 'finance@digitaliz.id',
            'role' => 'finance',
            'phone' => '6281234567802',
        ]);

        User::factory()->create([
            'name' => 'Hengki (Head of Digitaliz)',
            'email' => 'hengki@digitaliz.id',
            'role' => 'head',
            'phone' => '6281234567803',
        ]);

        User::factory()->count(5)->create(['role' => 'requester']);

        foreach ([
            ['OPR-001', 'Operasional & Umum'],
            ['MKT-001', 'Marketing'],
            ['ICT-001', 'ICT & Perangkat'],
            ['HRD-001', 'HR & Pengembangan'],
            ['LOG-001', 'Logistik & Transport'],
        ] as [$code, $description]) {
            BudgetCode::factory()->create([
                'code' => $code,
                'description' => $description,
            ]);
        }

        Setting::set('signer_name', 'Hengki');
        Setting::set('signer_title', 'Head of Digitaliz');
        Setting::set('company_name', 'Digitaliz');
    }
}
