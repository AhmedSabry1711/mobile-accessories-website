<?php
/**
 * Mobi Boost — fixed store categories.
 *
 * The catalogue has exactly five categories. They are stored with one canonical
 * English key (used in URLs, filters and the database) and translated for
 * display only, so the Arabic and English storefronts always point at the same
 * rows instead of duplicating the category.
 *
 * Legacy names ("Phone Cases", "Earbuds", …) are still accepted on read so old
 * links, old products and old admin forms keep working until the one-time
 * migration (api/migrate_fixed_categories.php) has run.
 */
declare(strict_types=1);

/** Canonical category keys, in the order they appear in the navigation. */
function mb_fixed_categories(): array {
    return ['CASES', 'CABLE', 'CHARGER', 'AUDIO', 'PROTECTIVE SCREEN'];
}

/**
 * Legacy label → canonical key. Keys are upper-cased and whitespace-collapsed
 * before lookup, so "phone   cases" and "جرابات" both resolve.
 */
function mb_category_aliases(): array {
    return [
        // cases
        'CASES' => 'CASES',
        'CASE' => 'CASES',
        'PHONE CASES' => 'CASES',
        'PHONE CASE' => 'CASES',
        'MOBILE CASES' => 'CASES',
        'COVERS' => 'CASES',
        'COVER' => 'CASES',
        'جرابات' => 'CASES',
        'جراب' => 'CASES',
        'كفرات' => 'CASES',
        'كفر' => 'CASES',
        // cable
        'CABLE' => 'CABLE',
        'CABLES' => 'CABLE',
        'DATA CABLES' => 'CABLE',
        'كابل' => 'CABLE',
        'كابلات' => 'CABLE',
        // charger
        'CHARGER' => 'CHARGER',
        'CHARGERS' => 'CHARGER',
        'POWER BANK' => 'CHARGER',
        'POWER BANKS' => 'CHARGER',
        'شاحن' => 'CHARGER',
        'شواحن' => 'CHARGER',
        'باور بانك' => 'CHARGER',
        // audio
        'AUDIO' => 'AUDIO',
        'EARBUDS' => 'AUDIO',
        'HEADPHONES' => 'AUDIO',
        'HEADSETS' => 'AUDIO',
        'SPEAKERS' => 'AUDIO',
        'صوتيات' => 'AUDIO',
        'سماعات' => 'AUDIO',
        'سماعة' => 'AUDIO',
        // protective screen
        'PROTECTIVE SCREEN' => 'PROTECTIVE SCREEN',
        'PROTECTIVE SCREENS' => 'PROTECTIVE SCREEN',
        'SCREEN PROTECTOR' => 'PROTECTIVE SCREEN',
        'SCREEN PROTECTORS' => 'PROTECTIVE SCREEN',
        'حماية الشاشة' => 'PROTECTIVE SCREEN',
        'واقيات شاشة' => 'PROTECTIVE SCREEN',
        'واقي شاشة' => 'PROTECTIVE SCREEN',
    ];
}

/** Normalize a category label for lookups. */
function mb_category_key(string $value): string {
    $value = trim($value);
    $value = preg_replace('/\s+/u', ' ', $value) ?? $value;
    return mb_strtoupper($value, 'UTF-8');
}

/**
 * Map any known label (English or Arabic, current or legacy) to its canonical
 * key. Unknown labels return '' — callers decide whether to reject or keep them.
 */
function mb_canonical_category(string $value): string {
    $key = mb_category_key($value);
    return mb_category_aliases()[$key] ?? '';
}

/**
 * Every database value that may still represent a canonical category. Used by
 * the product query so un-migrated rows keep showing up in the right filter.
 */
function mb_category_storage_values(string $value): array {
    $canonical = mb_canonical_category($value);
    if ($canonical === '') {
        return [$value];
    }
    $values = [$canonical, $value];
    foreach (mb_category_aliases() as $alias => $target) {
        if ($target === $canonical) {
            $values[] = $alias;
        }
    }
    return array_values(array_unique($values));
}

/** True when the label is one of the five fixed categories. */
function mb_is_fixed_category(string $value): bool {
    return in_array(mb_canonical_category($value), mb_fixed_categories(), true);
}
