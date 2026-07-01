<?php

declare(strict_types=1);

namespace App\Enums;

enum FailureReason: string
{
    case InsufficientFunds = 'insufficient_funds';
    case PayerNotFound = 'payer_not_found';
    case PayeeNotFound = 'payee_not_found';
    case InvalidAmount = 'invalid_amount';
    case CurrencyMismatch = 'currency_mismatch';
    case WalletLocked = 'wallet_locked';
    case IdempotencyConflict = 'idempotency_conflict';
    case SamePayerAndPayee = 'same_payer_and_payee';
    case PayerIsMerchant = 'payer_is_merchant';
    case WalletInactive = 'wallet_inactive';
    case AuthorizerRejected = 'authorizer_rejected';
    case Unknown = 'unknown';

    public function description(): string
    {
        return match ($this) {
            self::InsufficientFunds => 'Saldo insuficiente para realizar a transferência.',
            self::PayerNotFound => 'Carteira do pagador não encontrada.',
            self::PayeeNotFound => 'Carteira do beneficiário não encontrada.',
            self::InvalidAmount => 'O valor informado é inválido.',
            self::CurrencyMismatch => 'As carteiras possuem moedas diferentes.',
            self::WalletLocked => 'A carteira está temporariamente bloqueada.',
            self::IdempotencyConflict => 'Conflito de idempotência detectado.',
            self::SamePayerAndPayee => 'Pagador e beneficiário devem ser diferentes.',
            self::PayerIsMerchant => 'Lojistas não podem realizar transferências.',
            self::WalletInactive => 'A carteira informada está inativa.',
            self::AuthorizerRejected => 'Transferência não autorizada pelo serviço externo.',
            self::Unknown => 'Falha desconhecida.',
        };
    }
}
