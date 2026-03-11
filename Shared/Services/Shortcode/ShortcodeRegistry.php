<?php

declare(strict_types=1);

namespace App\Shared\Services\Shortcode;

use Qubus\Exception\Exception;

use function preg_match;
use function preg_match_all;
use function preg_replace_callback;
use function Qubus\Security\Helpers\esc_html;
use function trim;

use const PREG_SET_ORDER;

class ShortcodeRegistry
{
    /** @var Shortcode[] $shortcodes */
    protected array $shortcodes = [];

    protected array $context = [];

    protected bool $allowUnsafeHtml = false;

    public function __construct(array $context = [], bool $allowUnsafeHtml = false)
    {
        $this->context = $context;
        $this->allowUnsafeHtml = $allowUnsafeHtml;
    }

    public function allowUnsafeHtml(bool $allowed): void
    {
        $this->allowUnsafeHtml = $allowed;
    }

    public function register(Shortcode $shortcode): void
    {
        $this->shortcodes[$shortcode->tag()] = $shortcode;
    }

    public function render(string $content): string
    {
        // Recursively render nested shortcodes
        $previous = null;

        while ($previous !== $content) {
            $previous = $content;
            $content = $this->renderShortcodes($content);
        }

        return $content;
    }

    protected function renderShortcodes(string $content): string
    {
        $pattern = '/\[
            ([a-zA-Z0-9_]+)               # [1] tag name
            ([^\]\/]*?)                   # [2] attributes (non-greedy, not including / or ])
            (\/)?                         # [3] self-closing marker (optional "/")
        \](?:
            (.*?)                         # [4] content (optional, for enclosing)
            \[\/\1\]                      # closing tag
        )?/sx';

        return preg_replace_callback($pattern, function ($matches) {
            $tag = $matches[1];
            $attrString = trim($matches[2]);
            $isSelfClosing = isset($matches[3]) && $matches[3] === '/';
            $innerContent = $matches[4] ?? '';

            $attrs = $this->parseAttributes($attrString);

            // Apply conditional shortcode logic
            if (!$this->shouldRender($attrs)) {
                return ''; // Don't render if condition fails
            }

            if (isset($this->shortcodes[$tag])) {
                if (!$isSelfClosing) {
                    // Recursively render inner content for enclosing shortcodes
                    $innerContent = $this->render($innerContent);
                }

                $shortcode = $this->shortcodes[$tag];
                $output = $shortcode->render($attrs, $innerContent);

                // Respect global allowUnsafeHtml flag
                $isSafe = $shortcode->isSafe();

                if ($this->allowUnsafeHtml && $isSafe) {
                    return $output;
                }

                // Sanitize the output
                return $this->sanitizeOutput($output);
            }

            return $matches[0]; // Unknown shortcode, return as-is
        }, $content);
    }

    protected function parseAttributes(string $text): array
    {
        $attrs = [];
        preg_match_all('/(\w+)\s*=\s*"([^"]*)"/', $text, $matches, PREG_SET_ORDER);
        foreach ($matches as $match) {
            $attrs[$match[1]] = $match[2];
        }
        return $attrs;
    }

    /**
     * @throws Exception
     */
    protected function sanitizeOutput(string $output): string
    {
        return esc_html($output);
    }

    protected function shouldRender(array $attrs): bool
    {
        // Example: [hello show_if="logged_in"] or [hello if="user=admin"]
        if (isset($attrs['show_if']) && $attrs['show_if'] === 'false') {
            return false;
        }

        if (isset($attrs['hide_if']) && $attrs['hide_if'] === 'true') {
            return false;
        }

        // Example conditional syntax: if="user=admin"
        if (isset($attrs['if'])) {
            // You can extend this logic based on your app context
            return $this->evaluateCondition($attrs['if']);
        }

        return true;
    }

    protected function evaluateCondition(string $expression): bool
    {
        // Example: user=admin;logged_in=true
        $conditions = explode(';', $expression);

        foreach ($conditions as $condition) {
            // Parse simple expressions like key=value
            if (!preg_match('/^(\w+)\s*=\s*(.+)$/', $condition, $matches)) {
                return false;
            }

            $key = $matches[1];
            $expected = $matches[2];

            if (!isset($this->context[$key]) || (string) $this->context[$key] !== $expected) {
                return false;
            }
        }

        return true;
    }
}
