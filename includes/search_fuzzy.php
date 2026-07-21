<?php

declare(strict_types=1);

/**
 * Fuzzy text helpers for public catalog search (RU/TJ nicknames, Latin, ~60% match).
 */

function ia_search_norm(string $value): string
{
    $s = mb_strtolower(trim($value));
    $s = str_replace(['ё', '—', '–', '-', '_'], ['е', '', '', '', ''], $s);

    return preg_replace('/[^\p{L}\p{N}]+/u', '', $s) ?? '';
}

/**
 * @return array<string, string>
 */
function ia_search_aliases(): array
{
    return [
        'мер' => 'mercedes',
        'мерс' => 'mercedes',
        'мерин' => 'mercedes',
        'мерседес' => 'mercedes',
        'mer' => 'mercedes',
        'mers' => 'mercedes',
        'бмв' => 'bmw',
        'бэха' => 'bmw',
        'тойота' => 'toyota',
        'тойот' => 'toyota',
        'таёта' => 'toyota',
        'таета' => 'toyota',
        'тает' => 'toyota',
        'toy' => 'toyota',
        'toyota' => 'toyota',
        'ауди' => 'audi',
        'audi' => 'audi',
        'фолькс' => 'volkswagen',
        'фольксваген' => 'volkswagen',
        'волькс' => 'volkswagen',
        'vw' => 'volkswagen',
        'хонда' => 'honda',
        'honda' => 'honda',
        'ниссан' => 'nissan',
        'nissan' => 'nissan',
        'лексус' => 'lexus',
        'lexus' => 'lexus',
        'шевроле' => 'chevrolet',
        'шеви' => 'chevrolet',
        'chev' => 'chevrolet',
        'форд' => 'ford',
        'ford' => 'ford',
        'мазда' => 'mazda',
        'mazda' => 'mazda',
        'киа' => 'kia',
        'kia' => 'kia',
        'хендай' => 'hyundai',
        'хёндэ' => 'hyundai',
        'hyundai' => 'hyundai',
        'субару' => 'subaru',
        'subaru' => 'subaru',
        'тесла' => 'tesla',
        'tesla' => 'tesla',
        'порш' => 'porsche',
        'porsche' => 'porsche',
        'лада' => 'lada',
        'ваз' => 'lada',
        'lada' => 'lada',
    ];
}

function ia_search_cyr_to_lat(string $value): string
{
    static $map = [
        'а' => 'a', 'б' => 'b', 'в' => 'v', 'г' => 'g', 'д' => 'd', 'е' => 'e', 'ё' => 'e',
        'ж' => 'zh', 'з' => 'z', 'и' => 'i', 'й' => 'y', 'к' => 'k', 'л' => 'l', 'м' => 'm',
        'н' => 'n', 'о' => 'o', 'п' => 'p', 'р' => 'r', 'с' => 's', 'т' => 't', 'у' => 'u',
        'ф' => 'f', 'х' => 'h', 'ц' => 'ts', 'ч' => 'ch', 'ш' => 'sh', 'щ' => 'sch',
        'ъ' => '', 'ы' => 'y', 'ь' => '', 'э' => 'e', 'ю' => 'yu', 'я' => 'ya',
        'ҷ' => 'j', 'ғ' => 'g', 'қ' => 'q', 'ҳ' => 'h', 'ӣ' => 'i', 'ӯ' => 'u',
    ];

    $out = '';
    $len = mb_strlen($value);
    for ($i = 0; $i < $len; $i++) {
        $ch = mb_strtolower(mb_substr($value, $i, 1));
        $out .= $map[$ch] ?? $ch;
    }

    return $out;
}

/**
 * @return list<string>
 */
function ia_search_resolve_needles(string $raw): array
{
    $needles = [];
    $norm = ia_search_norm($raw);
    if ($norm !== '') {
        $needles[] = $norm;
    }

    $latin = ia_search_norm(ia_search_cyr_to_lat($raw));
    if ($latin !== '') {
        $needles[] = $latin;
    }

    $aliases = ia_search_aliases();
    if ($norm !== '' && isset($aliases[$norm])) {
        $needles[] = ia_search_norm($aliases[$norm]);
    }
    if ($latin !== '' && isset($aliases[$latin])) {
        $needles[] = ia_search_norm($aliases[$latin]);
    }

    foreach ($aliases as $key => $target) {
        $keyNorm = ia_search_norm($key);
        if ($keyNorm === '') {
            continue;
        }
        if ($norm !== '' && (str_starts_with($keyNorm, $norm) || str_starts_with($norm, $keyNorm))) {
            $needles[] = ia_search_norm($target);
            $needles[] = $keyNorm;
        }
        if ($latin !== '' && (str_starts_with($keyNorm, $latin) || str_starts_with($latin, $keyNorm))) {
            $needles[] = ia_search_norm($target);
        }
    }

    return array_values(array_unique(array_filter($needles)));
}

function ia_search_similarity(string $needle, string $haystack): float
{
    $a = ia_search_norm($needle);
    $b = ia_search_norm($haystack);
    if ($a === '' || $b === '') {
        return 0.0;
    }
    if (str_contains($b, $a) || str_contains($a, $b)) {
        return 100.0;
    }
    if (mb_strlen($a) >= 2 && str_starts_with($b, $a)) {
        return 100.0;
    }

    $best = 0.0;
    similar_text($a, $b, $pct);
    $best = max($best, (float) $pct);

    if (mb_strlen($a) >= 2 && mb_strlen($b) >= mb_strlen($a)) {
        $head = mb_substr($b, 0, min(mb_strlen($b), max(mb_strlen($a) + 4, 6)));
        similar_text($a, $head, $headPct);
        $best = max($best, (float) $headPct);
    }

    $latinA = ia_search_norm(ia_search_cyr_to_lat($needle));
    if ($latinA !== '' && $latinA !== $a) {
        similar_text($latinA, $b, $latPct);
        $best = max($best, (float) $latPct);
        if (mb_strlen($latinA) >= 2 && str_starts_with($b, $latinA)) {
            $best = 100.0;
        }
    }

    return $best;
}

/**
 * @return list<string>
 */
function ia_pub_fuzzy_resolve_terms(IaPgConnection|IaPdoConnection $pdo, string $q, float $minScore = 60.0): array
{
    $raw = trim($q);
    if ($raw === '' || mb_strlen($raw) < 2) {
        return [];
    }

    static $candidateCache = null;
    if ($candidateCache === null) {
        $candidateCache = [];
        try {
            foreach ($pdo->query('SELECT name FROM car_brands ORDER BY name') as $row) {
                $n = trim((string) ($row['name'] ?? ''));
                if ($n !== '') {
                    $candidateCache[$n] = true;
                }
            }
            foreach ($pdo->query("SELECT DISTINCT brand FROM ad_listings WHERE status = 'approved' AND brand <> '' LIMIT 500") as $row) {
                $n = trim((string) ($row['brand'] ?? ''));
                if ($n !== '') {
                    $candidateCache[$n] = true;
                }
            }
            foreach ($pdo->query("SELECT DISTINCT model FROM ad_listings WHERE status = 'approved' AND model <> '' LIMIT 500") as $row) {
                $n = trim((string) ($row['model'] ?? ''));
                if ($n !== '') {
                    $candidateCache[$n] = true;
                }
            }
            foreach ($pdo->query('SELECT name FROM car_models ORDER BY name LIMIT 500') as $row) {
                $n = trim((string) ($row['name'] ?? ''));
                if ($n !== '') {
                    $candidateCache[$n] = true;
                }
            }
        } catch (Throwable) {
            $candidateCache = [];
        }
    }

    $needles = ia_search_resolve_needles($raw);
    $terms = [];
    foreach (array_keys($candidateCache) as $name) {
        $score = ia_search_similarity($raw, $name);
        foreach ($needles as $needle) {
            $score = max($score, ia_search_similarity($needle, $name));
        }
        if ($score >= $minScore) {
            $terms[] = $name;
        }
    }

    return array_values(array_unique($terms));
}
