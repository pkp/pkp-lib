<?php

/**
 * @file classes/testing/Spec.php
 *
 * Copyright (c) 2026 Simon Fraser University
 * Copyright (c) 2026 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class Spec
 *
 * @brief Strict reader over a declarative scenario/bootstrap spec array.
 *
 * Builders read every key they support through get()/require()/children();
 * assertConsumed() then throws a SpecException naming (dotted) any key nobody
 * read. That enforces the design-record rule that an unsupported spec key is a
 * 400, never a silent no-op — and keeps the schema definition implicit in the
 * builder code that actually honours each key.
 */

namespace PKP\testing;

class Spec
{
    /** @var array<string, true> */
    private array $consumed = [];

    /** @var Spec[] child readers created so far (walked by assertConsumed) */
    private array $children = [];

    public function __construct(
        private readonly array $data,
        public readonly string $path = ''
    ) {
    }

    public function has(string $key): bool
    {
        return array_key_exists($key, $this->data);
    }

    /** Read an optional scalar/array value. Marks the key consumed. */
    public function get(string $key, mixed $default = null): mixed
    {
        $this->consumed[$key] = true;
        return $this->data[$key] ?? $default;
    }

    /** Read a required value; throws when absent or null. */
    public function require(string $key): mixed
    {
        $value = $this->get($key);
        if ($value === null) {
            throw new SpecException($this->keyPath($key), "Missing required spec key \"{$this->keyPath($key)}\"");
        }
        return $value;
    }

    /**
     * A multilingual value: pass through a locale map, wrap a bare string
     * under the given locale.
     */
    public function localized(string $key, string $locale, mixed $default = null): ?array
    {
        $value = $this->get($key, $default);
        if ($value === null) {
            return null;
        }
        return is_array($value) ? $value : [$locale => $value];
    }

    /** Read an optional nested object as a child Spec. */
    public function child(string $key): ?Spec
    {
        $value = $this->get($key);
        if ($value === null) {
            return null;
        }
        if (!is_array($value)) {
            throw new SpecException($this->keyPath($key), "Spec key \"{$this->keyPath($key)}\" must be an object");
        }
        $child = new Spec($value, $this->keyPath($key));
        $this->children[] = $child;
        return $child;
    }

    /**
     * Read an optional array of nested objects as child Specs.
     *
     * @return Spec[]
     */
    public function childList(string $key): array
    {
        $value = $this->get($key);
        if ($value === null) {
            return [];
        }
        if (!is_array($value) || ($value !== [] && array_keys($value) !== range(0, count($value) - 1))) {
            throw new SpecException($this->keyPath($key), "Spec key \"{$this->keyPath($key)}\" must be a list");
        }
        $children = [];
        foreach ($value as $i => $item) {
            if (!is_array($item)) {
                throw new SpecException("{$this->keyPath($key)}.{$i}", "Spec key \"{$this->keyPath($key)}.{$i}\" must be an object");
            }
            $child = new Spec($item, "{$this->keyPath($key)}.{$i}");
            $this->children[] = $child;
            $children[] = $child;
        }
        return $children;
    }

    /** Throw when any key (here or in any child read so far) went unread. */
    public function assertConsumed(): void
    {
        foreach (array_keys($this->data) as $key) {
            if (!isset($this->consumed[$key])) {
                throw new SpecException(
                    $this->keyPath((string) $key),
                    "Unsupported spec key \"{$this->keyPath((string) $key)}\""
                );
            }
        }
        foreach ($this->children as $child) {
            $child->assertConsumed();
        }
    }

    private function keyPath(string $key): string
    {
        return $this->path === '' ? $key : "{$this->path}.{$key}";
    }
}
