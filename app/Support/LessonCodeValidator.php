<?php

namespace App\Support;

use DOMDocument;
use DOMXPath;

class LessonCodeValidator
{
    public const ALLOWED_RULE_KINDS = [
        'html_includes',
        'css_includes',
        'js_includes',
        'selector_exists',
        'text_includes',
    ];

    public function validate(array $submittedCode, ?array $rules): array
    {
        $normalizedRules = self::normalizeRules($rules);

        if ($normalizedRules === []) {
            return [
                'passed' => false,
                'failures' => ['Esta lição não possui regras de validação configuradas.'],
            ];
        }

        $html = (string) ($submittedCode['html'] ?? '');
        $css = (string) ($submittedCode['css'] ?? '');
        $js = (string) ($submittedCode['js'] ?? '');

        $normalizedHtml = $this->normalizeContains($html);
        $normalizedCss = $this->normalizeContains($css);
        $normalizedJs = $this->normalizeContains($js);
        $document = $this->loadHtmlDocument($html);
        $bodyText = $document
            ? $this->normalizeContains($document->textContent ?? '')
            : '';

        $failures = [];

        foreach ($normalizedRules as $rule) {
            $expected = $this->normalizeContains($rule['value']);
            if ($expected === '') {
                continue;
            }

            if ($rule['kind'] === 'html_includes' && ! str_contains($normalizedHtml, $expected)) {
                $failures[] = 'HTML deve incluir: '.$rule['value'];

                continue;
            }

            if ($rule['kind'] === 'css_includes' && ! str_contains($normalizedCss, $expected)) {
                $failures[] = 'CSS deve incluir: '.$rule['value'];

                continue;
            }

            if ($rule['kind'] === 'js_includes' && ! str_contains($normalizedJs, $expected)) {
                $failures[] = 'JS deve incluir: '.$rule['value'];

                continue;
            }

            if ($rule['kind'] === 'text_includes' && ! str_contains($bodyText, $expected)) {
                $failures[] = 'Texto esperado não encontrado: '.$rule['value'];

                continue;
            }

            if ($rule['kind'] === 'selector_exists' && ! $this->selectorExists($document, $rule['value'])) {
                $failures[] = 'Elemento esperado não encontrado: '.$rule['value'];
            }
        }

        return [
            'passed' => $failures === [],
            'failures' => $failures,
        ];
    }

    public static function normalizeRules(?array $rules): array
    {
        if (! is_array($rules)) {
            return [];
        }

        $normalized = [];
        $seen = [];

        foreach ($rules as $rule) {
            if (! is_array($rule)) {
                continue;
            }

            $kind = trim((string) ($rule['kind'] ?? ''));
            $value = trim((string) ($rule['value'] ?? ''));

            if (! in_array($kind, self::ALLOWED_RULE_KINDS, true) || $value === '') {
                continue;
            }

            $key = strtolower($kind.':'.$value);
            if (isset($seen[$key])) {
                continue;
            }

            $seen[$key] = true;
            $normalized[] = [
                'kind' => $kind,
                'value' => $value,
            ];
        }

        return array_slice($normalized, 0, 12);
    }

    public static function deriveValidationRules(array $source): array
    {
        $html = (string) ($source['html'] ?? '');
        $css = (string) ($source['css'] ?? '');
        $js = (string) ($source['js'] ?? '');

        $rules = [];
        $seen = [];

        $pushRule = static function (string $kind, string $value) use (&$rules, &$seen): void {
            $trimmed = trim($value);
            if ($trimmed === '') {
                return;
            }

            $key = strtolower($kind.':'.$trimmed);
            if (isset($seen[$key])) {
                return;
            }

            $seen[$key] = true;
            $rules[] = [
                'kind' => $kind,
                'value' => $trimmed,
            ];
        };

        if ($html !== '') {
            preg_match_all('/id\s*=\s*["\']([^"\']+)["\']/i', $html, $idMatches);
            foreach (array_slice($idMatches[1] ?? [], 0, 4) as $id) {
                $pushRule('selector_exists', '#'.trim((string) $id));
            }

            preg_match_all('/class\s*=\s*["\']([^"\']+)["\']/i', $html, $classMatches);
            foreach (array_slice($classMatches[1] ?? [], 0, 4) as $classList) {
                $firstClass = preg_split('/\s+/', trim((string) $classList))[0] ?? null;
                if ($firstClass) {
                    $pushRule('selector_exists', '.'.$firstClass);
                }
            }

            if (count($rules) < 2) {
                preg_match_all('/<([a-z][a-z0-9-]*)\b/i', $html, $tagMatches);
                $tags = array_values(array_unique(array_map('strtolower', $tagMatches[1] ?? [])));
                $ignored = ['html', 'head', 'body', 'meta', 'title', 'style', 'script', 'link'];
                foreach ($tags as $tag) {
                    if (in_array($tag, $ignored, true)) {
                        continue;
                    }

                    $pushRule('html_includes', '<'.$tag);
                    if (count($rules) >= 3) {
                        break;
                    }
                }
            }
        }

        if ($css !== '') {
            preg_match_all('/(^|\n)\s*([^@\n][^{]+)\{/m', $css, $selectorMatches);
            foreach (array_slice($selectorMatches[2] ?? [], 0, 3) as $selectorGroup) {
                $first = trim(explode(',', (string) $selectorGroup)[0] ?? '');
                if ($first !== '' && strlen($first) < 80) {
                    $pushRule('css_includes', $first);
                }
            }
        }

        if ($js !== '') {
            $signals = ['addEventListener', 'querySelector', 'getElementById', 'classList', 'textContent', 'innerHTML'];
            foreach ($signals as $signal) {
                if (str_contains($js, $signal)) {
                    $pushRule('js_includes', $signal);
                }
            }
        }

        return array_slice($rules, 0, 8);
    }

    private function normalizeContains(string $value): string
    {
        $normalized = preg_replace('/\s+/', ' ', strtolower($value));

        return trim($normalized ?? '');
    }

    private function loadHtmlDocument(string $html): ?DOMDocument
    {
        $source = trim($html);
        if ($source === '') {
            $source = '<body></body>';
        }

        $document = new DOMDocument;
        $previous = libxml_use_internal_errors(true);

        try {
            $loaded = $document->loadHTML('<?xml encoding="utf-8" ?>'.$source, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }

        return $loaded ? $document : null;
    }

    private function selectorExists(?DOMDocument $document, string $selector): bool
    {
        if (! $document) {
            return false;
        }

        $selector = trim($selector);
        if ($selector === '') {
            return false;
        }

        $xpath = new DOMXPath($document);

        if (str_starts_with($selector, '#')) {
            $id = substr($selector, 1);
            if ($id === '') {
                return false;
            }

            $escaped = $this->escapeXpathValue($id);

            return (int) $xpath->evaluate('count(//*[@id='.$escaped.'])') > 0;
        }

        if (str_starts_with($selector, '.')) {
            $className = substr($selector, 1);
            if ($className === '') {
                return false;
            }

            $escaped = $this->escapeXpathValue($className);

            return (int) $xpath->evaluate('count(//*[contains(concat(" ", normalize-space(@class), " "), concat(" ", '.$escaped.', " "))])') > 0;
        }

        if (! preg_match('/^[a-z][a-z0-9-]*$/i', $selector)) {
            return false;
        }

        return (int) $xpath->evaluate('count(//'.strtolower($selector).')') > 0;
    }

    private function escapeXpathValue(string $value): string
    {
        if (! str_contains($value, "'")) {
            return "'{$value}'";
        }

        if (! str_contains($value, '"')) {
            return '"'.$value.'"';
        }

        $parts = explode("'", $value);

        return "concat('".implode("', \"'\", '", $parts)."')";
    }
}
