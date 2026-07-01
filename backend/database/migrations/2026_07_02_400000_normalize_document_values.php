<?php

use App\ValueObjects\DocumentValueNormalizer;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

return new class extends Migration
{
    /**
     * @var list<string>
     */
    private const DOCUMENT_TYPES_TO_NORMALIZE = ['br_cpf', 'br_cnpj'];

    public function up(): void
    {
        DB::table('users')
            ->whereIn('document_type', self::DOCUMENT_TYPES_TO_NORMALIZE)
            ->orderBy('id')
            ->chunkById(500, function ($users): void {
                foreach ($users as $user) {
                    $this->normalizeUserDocumentValue($user);
                }
            });
    }

    private function normalizeUserDocumentValue(mixed $user): void
    {
        if (!is_string($user->document_type) || !is_string($user->document_value)) {
            Log::warning('Skipping user with invalid document data during normalization', [
                'id' => $user->id ?? null,
                'document_type' => $user->document_type ?? null,
            ]);

            return;
        }

        try {
            $normalized = DocumentValueNormalizer::normalize($user->document_type, $user->document_value);
        } catch (\Throwable $exception) {
            Log::warning('Failed to normalize document value', [
                'id' => $user->id,
                'document_type' => $user->document_type,
                'error' => $exception->getMessage(),
            ]);

            return;
        }

        if ($normalized === $user->document_value) {
            return;
        }

        DB::table('users')
            ->where('id', $user->id)
            ->update(['document_value' => $normalized]);
    }

    public function down(): void
    {
        // Backfill to canonical form is destructive and cannot be safely reversed.
    }
};
