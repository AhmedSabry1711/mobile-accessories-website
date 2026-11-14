<?php
/**
 * Mobi Boost — catalogue search.
 *
 * Search covers what a shopper actually knows: the product name, the category,
 * and every phone model / brand the product is compatible with. Variant models
 * live in a child table, so scoring happens in PHP on the documents already
 * loaded by the store layer.
 *
 * The scorer is intentionally forgiving: each word of the query contributes to
 * the score, so "Samsung A55" still finds the Samsung cases even though the
 * catalogue only lists the S24/S25 models. Exact name/model matches rank first.
 */
declare(strict_types=1);

/** Lower-case and collapse whitespace. */
function mb_search_normalize(string $value): string {
    $value = mb_strtolower(trim($value), 'UTF-8');
    return preg_replace('/\s+/u', ' ', $value) ?? $value;
}

/** Significant query words (2+ characters). */
function mb_search_tokens(string $query): array {
    $tokens = preg_split('/\s+/u', mb_search_normalize($query)) ?: [];
    $tokens = array_values(array_filter($tokens, fn($token) => mb_strlen($token) >= 2));
    return array_values(array_unique($tokens));
}

/** Arabic category label, used so Arabic searches hit the right products. */
function mb_category_arabic(string $category): string {
    return [
        'CASES' => 'جرابات',
        'CABLE' => 'كابلات',
        'CHARGER' => 'شواحن',
        'AUDIO' => 'صوتيات',
        'PROTECTIVE SCREEN' => 'حماية الشاشة',
    ][$category] ?? '';
}

/** Brand names implied by the compatible phone models (Apple, Galaxy, Redmi…). */
function mb_search_brand_terms(string $modelsText): string {
    $extra = [];
    if (strpos($modelsText, 'iphone') !== false || strpos($modelsText, 'apple') !== false) {
        $extra[] = 'apple';
    }
    if (strpos($modelsText, 'samsung') !== false || strpos($modelsText, 'galaxy') !== false) {
        $extra[] = 'galaxy';
    }
    if (preg_match('/xiaomi|redmi|poco/', $modelsText)) {
        $extra[] = 'redmi poco';
    }
    return implode(' ', $extra);
}

/**
 * Relevance score for a product against a search query. 0 means "no match".
 */
function mb_search_score(array $product, string $query): int {
    $q = mb_search_normalize($query);
    if ($q === '') {
        return 0;
    }
    $tokens = mb_search_tokens($q);
    if (!$tokens) {
        return 0;
    }

    $name = mb_search_normalize((string)($product['name'] ?? ''));
    $rawCategory = (string)($product['category'] ?? '');
    $categoryKey = mb_canonical_category($rawCategory);
    if ($categoryKey === '') {
        $categoryKey = $rawCategory;
    }
    $category = mb_search_normalize($categoryKey . ' ' . mb_category_arabic($categoryKey));
    $description = mb_search_normalize((string)($product['description'] ?? ''));

    $models = [];
    foreach ((array)($product['variants'] ?? []) as $variant) {
        $model = (string)($variant['model'] ?? '');
        if ($model !== '') {
            $models[] = $model;
        }
    }
    $modelsText = mb_search_normalize(implode(' ', $models));
    // Brand aliases are matched as if they were part of the compatible models.
    $modelsText = mb_search_normalize($modelsText . ' ' . mb_search_brand_terms($modelsText));

    $score = 0;
    if ($name !== '' && strpos($name, $q) !== false) {
        $score += 80;
    }
    if ($modelsText !== '' && strpos($modelsText, $q) !== false) {
        $score += 70;
    }
    if ($category !== '' && strpos($category, $q) !== false) {
        $score += 50;
    }
    if ($description !== '' && strpos($description, $q) !== false) {
        $score += 15;
    }

    $matched = 0;
    foreach ($tokens as $token) {
        if ($name !== '' && strpos($name, $token) !== false) {
            $score += 14;
        } elseif ($modelsText !== '' && strpos($modelsText, $token) !== false) {
            $score += 12;
        } elseif ($category !== '' && strpos($category, $token) !== false) {
            $score += 8;
        } elseif ($description !== '' && strpos($description, $token) !== false) {
            $score += 3;
        } else {
            continue;
        }
        $matched++;
    }

    if ($matched === 0) {
        return 0;
    }
    if ($matched === count($tokens)) {
        $score += 25;
    }
    return $score;
}
