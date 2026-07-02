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
            self::InsufficientFunds => 'Insufficient balance to complete the transfer.',
            self::PayerNotFound => 'Payer wallet not found.',
            self::PayeeNotFound => 'Payee wallet not found.',
            self::InvalidAmount => 'The amount provided is invalid.',
            self::CurrencyMismatch => 'The wallets have different currencies.',
            self::WalletLocked => 'The wallet is temporarily locked.',
            self::IdempotencyConflict => 'Idempotency conflict detected.',
            self::SamePayerAndPayee => 'Payer and payee must be different.',
            self::PayerIsMerchant => 'Merchants cannot make transfers.',
            self::WalletInactive => 'The provided wallet is inactive.',
            self::AuthorizerRejected => 'Transfer not authorized by the external service.',
            self::Unknown => 'Unknown failure.',
        };
    }
}
