<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\DocumentType;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class DocumentValueBackfillMigrationTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_it_backfills_masked_cpf_and_cnpj_records(): void
    {
        DB::table('users')->insert([
            [
                'name' => 'Masked CPF User',
                'email' => 'masked-cpf@example.com',
                'password' => 'password',
                'type' => 'common',
                'document_country' => 'BRA',
                'document_type' => DocumentType::BrCpf->value,
                'document_value' => '529.982.247-25',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Masked CNPJ User',
                'email' => 'masked-cnpj@example.com',
                'password' => 'password',
                'type' => 'merchant',
                'document_country' => 'BRA',
                'document_type' => DocumentType::BrCnpj->value,
                'document_value' => '11.222.333/0001-81',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Lowercase CNPJ User',
                'email' => 'lowercase-cnpj@example.com',
                'password' => 'password',
                'type' => 'merchant',
                'document_country' => 'BRA',
                'document_type' => DocumentType::BrCnpj->value,
                'document_value' => '12.abc.345/0001-88',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $this->artisan('migrate', [
            '--path' => 'database/migrations/2026_07_02_400000_normalize_document_values.php',
            '--force' => true,
        ]);

        $this->assertDatabaseHas('users', [
            'email' => 'masked-cpf@example.com',
            'document_value' => '52998224725',
        ]);

        $this->assertDatabaseHas('users', [
            'email' => 'masked-cnpj@example.com',
            'document_value' => '11222333000181',
        ]);

        $this->assertDatabaseHas('users', [
            'email' => 'lowercase-cnpj@example.com',
            'document_value' => '12ABC345000188',
        ]);
    }
}
