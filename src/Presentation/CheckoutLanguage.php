<?php
/** Checkout text is scoped to a render call; WordPress locale is never changed. */
namespace EilmoCheckout\Presentation;
final class CheckoutLanguage {
    private static $stack = array();
    private static $catalogs = array();
    public static function catalog(string $language): array {
        $language = $language === 'bn' ? 'bn' : 'en';
        if (!isset(self::$catalogs[$language])) self::$catalogs[$language] = require __DIR__.'/Languages/'.$language.'.php';
        return self::$catalogs[$language];
    }
    public static function current(): string { return self::$stack ? end(self::$stack) : 'en'; }
    public static function in_language(string $language, callable $render) {
        self::$stack[] = $language === 'bn' ? 'bn' : 'en';
        try { return $render(); } finally { array_pop(self::$stack); }
    }
    public static function text(string $key, string $language, array $params = array(), ?string $override = null): string {
        if ($override !== null) return $override;
        $value = self::catalog($language)[$key] ?? self::catalog('en')[$key] ?? $key;
        foreach ($params as $name=>$replacement) $value = str_replace('{'.$name.'}', (string)$replacement, $value);
        return $value;
    }
    /** Translate only known plugin defaults. Unknown/custom copy is preserved. */
    public static function copy(string $source, ?string $language = null): string {
        $language = $language ?? self::current();
        if ($language !== 'bn') return $source;
        $key = array_search($source,self::catalog('en'),true);
        return $key === false ? $source : self::text($key,$language);
    }
    /**
     * Resolve merchant-authored bilingual data without guessing a translation.
     *
     * English stays in the historical base key for backwards compatibility.
     * A non-empty `<key>_bn` value is used only while Bangla is active. If the
     * Bangla field is empty, the original merchant value is preserved.
     */
    public static function localized_field(array $record, string $key, ?string $language = null): string {
        $language = $language ?? self::current();
        $language = $language === 'bn' ? 'bn' : 'en';

        $localized_key = $key . '_' . $language;
        if (array_key_exists($localized_key, $record) && is_scalar($record[$localized_key])) {
            $localized = trim((string) $record[$localized_key]);
            if ($localized !== '') return (string) $record[$localized_key];
        }

        return isset($record[$key]) && is_scalar($record[$key])
            ? (string) $record[$key]
            : '';
    }

    /**
     * Apply optional per-language overrides, then translate known plugin defaults.
     * Unknown/custom copy is still preserved exactly as entered by the merchant.
     */
    public static function defaults(array $settings): array {
        $language = self::current();

        foreach (array_keys($settings) as $key) {
            if (!is_string($key) || preg_match('/_(?:en|bn)$/', $key)) continue;
            $localized_key = $key . '_' . $language;
            if (array_key_exists($localized_key, $settings) && is_scalar($settings[$localized_key])) {
                $localized = trim((string) $settings[$localized_key]);
                if ($localized !== '') $settings[$key] = (string) $settings[$localized_key];
            }
        }

        foreach ($settings as $key=>$value) {
            if (is_array($value)) $settings[$key] = self::defaults($value);
            elseif (is_string($value)) $settings[$key] = self::copy($value);
        }
        return $settings;
    }
    public static function translate_response(array $response, string $language): array {
        foreach ($response as $key=>$value) {
            if (is_array($value)) $response[$key] = self::translate_response($value,$language);
            elseif (is_string($value) && in_array($key,array('message','error','notice'),true)) $response[$key] = self::copy($value,$language);
        }
        return $response;
    }
}
