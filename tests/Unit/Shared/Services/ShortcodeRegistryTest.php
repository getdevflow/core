<?php

declare(strict_types=1);

use App\Shared\Services\Shortcode\BaseShortcode;
use App\Shared\Services\Shortcode\ShortcodeRegistry;

beforeEach(function (): void {
    $this->shortcodeForTest = static fn (bool $safe = false): BaseShortcode => new class ($safe) extends BaseShortcode {
        public function __construct(private readonly bool $safe)
        {
        }

        public function tag(): string
        {
            return 'hello-world';
        }

        protected function handle(array $attrs, ?string $content = null): string
        {
            return '<strong>' . ($attrs['name'] ?? $content ?? '') . '</strong>';
        }

        public function isSafe(): bool
        {
            return $this->safe;
        }
    };
});

it('renders self-closing shortcodes and common attribute syntaxes', function (string $attribute): void {
    $registry = new ShortcodeRegistry(allowUnsafeHtml: true);
    $registry->register(($this->shortcodeForTest)(safe: true));

    expect($registry->render('[hello-world name=' . $attribute . ' /]'))->toBe('<strong>Joshua</strong>');
})->with(['"Joshua"', "'Joshua'", 'Joshua']);

it('escapes shortcode output unless both safety switches allow html', function (): void {
    $registry = new ShortcodeRegistry(allowUnsafeHtml: true);
    $registry->register(($this->shortcodeForTest)(safe: false));

    expect($registry->render('[hello-world]Joshua[/hello-world]'))
        ->toBe('&lt;strong&gt;Joshua&lt;/strong&gt;');
});

it('evaluates shortcode conditions against context', function (): void {
    $registry = new ShortcodeRegistry(['role' => 'editor'], true);
    $registry->register(($this->shortcodeForTest)(safe: true));

    expect($registry->render('[hello-world if="role=admin" name="No" /]'))->toBe('')
        ->and($registry->render('[hello-world if="role=editor" name="Yes" /]'))->toBe('<strong>Yes</strong>');
});
