<?php

declare(strict_types=1);

/**
 * VIP / TOP: 6 месяцев бесплатно от даты старта сайта, затем платно.
 * Админ: promotion_monetization_enabled = 0 → всегда бесплатно.
 */
function ia_promotion_ensure_settings(IaPgConnection|IaPdoConnection $pdo): void
{
    $defaults = [
        'promotion_free_months' => '6',
        'promotion_monetization_enabled' => '1',
    ];
    foreach ($defaults as $key => $value) {
        if (ia_site_setting_get($pdo, $key) === '') {
            ia_site_setting_set($pdo, $key, $value);
        }
    }
    if (ia_site_setting_get($pdo, 'promotion_launch_at') === '') {
        ia_site_setting_set($pdo, 'promotion_launch_at', (new DateTimeImmutable())->format('Y-m-d H:i:s'));
    }
}

function ia_promotion_free_months(IaPgConnection|IaPdoConnection $pdo): int
{
    ia_promotion_ensure_settings($pdo);
    $m = (int) ia_site_setting_get($pdo, 'promotion_free_months', '6');

    return max(1, min(36, $m));
}

function ia_promotion_launch_at(IaPgConnection|IaPdoConnection $pdo): DateTimeImmutable
{
    ia_promotion_ensure_settings($pdo);
    $raw = trim(ia_site_setting_get($pdo, 'promotion_launch_at', ''));
    if ($raw === '') {
        return new DateTimeImmutable();
    }
    try {
        return new DateTimeImmutable($raw);
    } catch (\Throwable) {
        return new DateTimeImmutable();
    }
}

function ia_promotion_grace_ends_at(IaPgConnection|IaPdoConnection $pdo): DateTimeImmutable
{
    $launch = ia_promotion_launch_at($pdo);

    return $launch->modify('+' . ia_promotion_free_months($pdo) . ' months');
}

/** Монетизация включена админом (если выключена — VIP/TOP всегда бесплатно). */
function ia_promotion_monetization_enabled(IaPgConnection|IaPdoConnection $pdo): bool
{
    ia_promotion_ensure_settings($pdo);

    return ia_site_setting_get($pdo, 'promotion_monetization_enabled', '1') === '1';
}

/** Нужно ли взимать плату за VIP/TOP сейчас. */
function ia_promotion_paid_required(IaPgConnection|IaPdoConnection $pdo): bool
{
    if (!ia_promotion_monetization_enabled($pdo)) {
        return false;
    }

    return new DateTimeImmutable() >= ia_promotion_grace_ends_at($pdo);
}

/**
 * @return array{
 *   monetization_enabled: bool,
 *   paid_required: bool,
 *   in_grace_period: bool,
 *   launch_at: DateTimeImmutable,
 *   grace_ends_at: DateTimeImmutable,
 *   free_months: int,
 *   days_left: int
 * }
 */
function ia_promotion_status(IaPgConnection|IaPdoConnection $pdo): array
{
    $launch = ia_promotion_launch_at($pdo);
    $graceEnds = ia_promotion_grace_ends_at($pdo);
    $now = new DateTimeImmutable();
    $paidRequired = ia_promotion_paid_required($pdo);
    $daysLeft = 0;
    $secondsLeft = 0;
    if ($now < $graceEnds) {
        $secondsLeft = $graceEnds->getTimestamp() - $now->getTimestamp();
        $daysLeft = (int) max(0, (int) ceil($secondsLeft / 86400));
    }

    return [
        'monetization_enabled' => ia_promotion_monetization_enabled($pdo),
        'paid_required' => $paidRequired,
        'in_grace_period' => !$paidRequired && ia_promotion_monetization_enabled($pdo),
        'launch_at' => $launch,
        'grace_ends_at' => $graceEnds,
        'grace_ends_iso' => $graceEnds->format('c'),
        'free_months' => ia_promotion_free_months($pdo),
        'days_left' => $daysLeft,
        'seconds_left' => $secondsLeft,
    ];
}

function ia_promotion_reset_launch_now(IaPgConnection|IaPdoConnection $pdo): void
{
    ia_site_setting_set($pdo, 'promotion_launch_at', (new DateTimeImmutable())->format('Y-m-d H:i:s'));
}

function ia_promotion_tariff_price(IaPgConnection|IaPdoConnection $pdo, string $code): float
{
    $code = strtolower(trim($code));
    if (!in_array($code, ['vip', 'top'], true)) {
        return 0.0;
    }
    try {
        $st = $pdo->prepare('SELECT price FROM billing_tariffs WHERE code = ? LIMIT 1');
        $st->execute([$code]);
        $v = $st->fetchColumn();
        if ($v !== false) {
            return max(0.0, (float) $v);
        }
    } catch (\PDOException) {
        // billing_tariffs может отсутствовать на старом деплое
    }

    return match ($code) {
        'vip' => 450.0,
        'top' => 150.0,
        default => 0.0,
    };
}

function ia_promotion_tariff_days(IaPgConnection|IaPdoConnection $pdo, string $code): ?int
{
    $code = strtolower(trim($code));
    try {
        $st = $pdo->prepare('SELECT duration_days FROM billing_tariffs WHERE code = ? LIMIT 1');
        $st->execute([$code]);
        $v = $st->fetchColumn();
        if ($v !== false && $v !== null && $v !== '') {
            return (int) $v;
        }
    } catch (\PDOException) {
    }

    return match ($code) {
        'vip' => 60,
        'top' => 30,
        default => null,
    };
}

function ia_promotion_format_price(float $amount): string
{
    if ($amount <= 0) {
        return '0 TJS';
    }

    return rtrim(rtrim(number_format($amount, 2, '.', ' '), '0'), '.') . ' TJS';
}

/**
 * @return array{
 *   promotion: string,
 *   is_vip: bool,
 *   is_top: bool,
 *   paid_required: bool,
 *   price: float,
 *   price_label: string,
 *   is_free_now: bool
 * }
 */
function ia_promotion_resolve(IaPgConnection|IaPdoConnection $pdo, string $promotion): array
{
    $promotion = strtolower(trim($promotion));
    if (!in_array($promotion, ['normal', 'top', 'vip'], true)) {
        $promotion = 'normal';
    }
    $paidRequired = ia_promotion_paid_required($pdo);
    $isVip = $promotion === 'vip';
    $isTop = $promotion === 'top';
    $price = 0.0;
    if (($isVip || $isTop) && $paidRequired) {
        $price = ia_promotion_tariff_price($pdo, $promotion);
    }
    $isFreeNow = ($isVip || $isTop) && !$paidRequired;

    return [
        'promotion' => $promotion,
        'is_vip' => $isVip,
        'is_top' => $isTop,
        'paid_required' => $paidRequired,
        'price' => $price,
        'price_label' => $isFreeNow ? 'Бесплатно' : ia_promotion_format_price($price),
        'is_free_now' => $isFreeNow,
    ];
}

/** Ошибка для пользователя или null (оплата — на отдельном шаге). */
function ia_promotion_validate_listing_choice(IaPgConnection|IaPdoConnection $pdo, string $promotion, ?string $previousPromotion = null): ?string
{
    $promotion = strtolower(trim($promotion));
    if (!in_array($promotion, ['normal', 'top', 'vip'], true)) {
        return 'Некорректный тариф размещения.';
    }

    return null;
}

function ia_promotion_apply_to_flags(string $promotion): array
{
    $promotion = strtolower(trim($promotion));

    return [
        'is_vip' => $promotion === 'vip' ? 1 : 0,
        'is_top' => $promotion === 'top' ? 1 : 0,
    ];
}
