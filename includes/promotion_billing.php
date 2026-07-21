<?php

declare(strict_types=1);

require_once IA_ROOT . '/includes/promotion_monetization.php';

function ia_promotion_billing_note_code(string $code): string
{
    return 'listing_promo:' . strtolower(trim($code));
}

function ia_promotion_billing_parse_note(?string $note): ?string
{
    if ($note === null || $note === '') {
        return null;
    }
    if (preg_match('/^listing_promo:(vip|top)$/i', trim($note), $m)) {
        return strtolower($m[1]);
    }

    return null;
}

/**
 * @return array{id:int,code:string,name:string,price:float,duration_days:?int}|null
 */
function ia_promotion_tariff_row(IaPgConnection|IaPdoConnection $pdo, string $code): ?array
{
    $code = strtolower(trim($code));
    if (!in_array($code, ['vip', 'top'], true)) {
        return null;
    }
    try {
        $st = $pdo->prepare('SELECT id, code, name, price, duration_days FROM billing_tariffs WHERE code = ? LIMIT 1');
        $st->execute([$code]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            return null;
        }

        return [
            'id' => (int) $row['id'],
            'code' => (string) $row['code'],
            'name' => (string) $row['name'],
            'price' => max(0.0, (float) $row['price']),
            'duration_days' => isset($row['duration_days']) && $row['duration_days'] !== null && $row['duration_days'] !== ''
                ? (int) $row['duration_days']
                : null,
        ];
    } catch (\PDOException) {
        return null;
    }
}

function ia_promotion_payment_required_for_choice(IaPgConnection|IaPdoConnection $pdo, string $promotion): bool
{
    $promotion = strtolower(trim($promotion));
    if (!in_array($promotion, ['vip', 'top'], true)) {
        return false;
    }

    return ia_promotion_paid_required($pdo);
}

/**
 * @return array{transaction_id:int,code:string,amount:float,tariff_name:string}|null
 */
function ia_promotion_listing_pending_payment(IaPgConnection|IaPdoConnection $pdo, int $listingId): ?array
{
    if ($listingId <= 0 || !ia_db_column_exists($pdo, 'billing_transactions', 'listing_id')) {
        return null;
    }
    try {
        $st = $pdo->prepare(
            "SELECT t.id, t.amount, t.note, tr.code, tr.name
             FROM billing_transactions t
             LEFT JOIN billing_tariffs tr ON tr.id = t.tariff_id
             WHERE t.listing_id = ? AND t.status = 'pending'
             ORDER BY t.id DESC LIMIT 1"
        );
        $st->execute([$listingId]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            return null;
        }
        $code = ia_promotion_billing_parse_note(isset($row['note']) ? (string) $row['note'] : null)
            ?? strtolower((string) ($row['code'] ?? ''));
        if (!in_array($code, ['vip', 'top'], true)) {
            return null;
        }

        return [
            'transaction_id' => (int) $row['id'],
            'code' => $code,
            'amount' => (float) $row['amount'],
            'tariff_name' => (string) ($row['name'] ?? strtoupper($code)),
        ];
    } catch (\PDOException) {
        return null;
    }
}

function ia_promotion_listing_awaiting_payment(IaPgConnection|IaPdoConnection $pdo, int $listingId): bool
{
    return ia_promotion_listing_pending_payment($pdo, $listingId) !== null;
}

function ia_promotion_create_listing_payment(
    IaPgConnection|IaPdoConnection $pdo,
    int $userId,
    int $listingId,
    string $code
): ?int {
    if ($listingId <= 0 || $userId <= 0) {
        return null;
    }
    $tariff = ia_promotion_tariff_row($pdo, $code);
    $amount = $tariff !== null ? $tariff['price'] : ia_promotion_tariff_price($pdo, $code);
    $tariffId = $tariff !== null ? $tariff['id'] : null;
    $note = ia_promotion_billing_note_code($code);

    $hasListingCol = ia_db_column_exists($pdo, 'billing_transactions', 'listing_id');
    if ($hasListingCol) {
        $st = $pdo->prepare(
            "SELECT id FROM billing_transactions WHERE listing_id = ? AND status = 'pending' LIMIT 1"
        );
        $st->execute([$listingId]);
        $existing = $st->fetchColumn();
        if ($existing !== false) {
            return (int) $existing;
        }
    }

    if ($hasListingCol) {
        $ins = $pdo->prepare(
            'INSERT INTO billing_transactions (platform_user_id, listing_id, tariff_id, amount, status, method, note)
             VALUES (?, ?, ?, ?, \'pending\', \'unknown\', ?)'
        );
        $ins->execute([$userId, $listingId, $tariffId, $amount, $note]);
    } else {
        $ins = $pdo->prepare(
            'INSERT INTO billing_transactions (platform_user_id, tariff_id, amount, status, method, note)
             VALUES (?, ?, ?, \'pending\', \'unknown\', ?)'
        );
        $ins->execute([$userId, $tariffId, $amount, $note . '|listing:' . $listingId]);
    }

    $newId = ia_db_last_insert_id($pdo, null);

    return $newId > 0 ? $newId : null;
}

function ia_promotion_apply_tariff_to_listing(IaPgConnection|IaPdoConnection $pdo, int $listingId, string $code): void
{
    $flags = ia_promotion_apply_to_flags($code);
    $pdo->prepare('UPDATE ad_listings SET is_vip = ?, is_top = ? WHERE id = ?')
        ->execute([$flags['is_vip'], $flags['is_top'], $listingId]);
}

function ia_promotion_complete_payment(
    IaPgConnection|IaPdoConnection $pdo,
    int $transactionId,
    int $userId,
    string $method
): bool {
    $allowed = ['card', 'sbp', 'bank_transfer', 'cash'];
    if (!in_array($method, $allowed, true)) {
        $method = 'card';
    }
    $st = $pdo->prepare(
        "SELECT t.id, t.listing_id, t.note, t.platform_user_id, t.status
         FROM billing_transactions t WHERE t.id = ? LIMIT 1"
    );
    $st->execute([$transactionId]);
    $row = $st->fetch(PDO::FETCH_ASSOC);
    if (!$row || (int) ($row['platform_user_id'] ?? 0) !== $userId) {
        return false;
    }
    if ((string) ($row['status'] ?? '') === 'paid') {
        return true;
    }

    $listingId = (int) ($row['listing_id'] ?? 0);
    if ($listingId <= 0 && isset($row['note'])) {
        if (preg_match('/listing:(\d+)/', (string) $row['note'], $m)) {
            $listingId = (int) $m[1];
        }
    }
    $code = ia_promotion_billing_parse_note(isset($row['note']) ? (string) $row['note'] : null);
    if ($code === null || $listingId <= 0) {
        return false;
    }

    $pdo->prepare(
        "UPDATE billing_transactions SET status = 'paid', method = ?, paid_at = NOW() WHERE id = ?"
    )->execute([$method, $transactionId]);

    ia_promotion_apply_tariff_to_listing($pdo, $listingId, $code);

    return true;
}

function ia_promotion_can_admin_approve_listing(IaPgConnection|IaPdoConnection $pdo, int $listingId): bool
{
    return !ia_promotion_listing_awaiting_payment($pdo, $listingId);
}
