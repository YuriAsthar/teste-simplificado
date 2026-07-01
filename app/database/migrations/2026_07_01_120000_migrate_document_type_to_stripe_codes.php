<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('users')
            ->where('document_type', 'cpf')
            ->update(['document_type' => 'br_cpf']);

        DB::table('users')
            ->where('document_type', 'cnpj')
            ->update(['document_type' => 'br_cnpj']);
    }

    public function down(): void
    {
        DB::table('users')
            ->where('document_type', 'br_cpf')
            ->update(['document_type' => 'cpf']);

        DB::table('users')
            ->where('document_type', 'br_cnpj')
            ->update(['document_type' => 'cnpj']);
    }
};
