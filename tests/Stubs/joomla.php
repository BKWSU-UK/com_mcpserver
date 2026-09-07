<?php

/**
 * @package     MCP Server for Joomla
 * @copyright   Copyright (C) 2026 Onepoint Consulting Ltd
 * @license     GNU General Public License version 2 or later; see LICENSE
 */

declare(strict_types=1);

namespace Joomla\CMS {
    class Factory
    {
        public static ?object $application = null;

        public static ?object $database = null;

        public static function getApplication(): object
        {
            if (self::$application === null) {
                throw new \RuntimeException('Test application has not been installed');
            }

            return self::$application;
        }

        public static function getDbo(): object
        {
            if (self::$database === null) {
                throw new \RuntimeException('Test database has not been installed');
            }

            return self::$database;
        }

        public static function reset(): void
        {
            self::$application = null;
            self::$database = null;
        }
    }
}

namespace Joomla\CMS\Component {
    use Joomla\Registry\Registry;

    class ComponentHelper
    {
        /** @var array<string, Registry> */
        public static array $params = [];

        public static function getParams(string $option): Registry
        {
            return self::$params[$option] ?? new Registry();
        }

        public static function reset(): void
        {
            self::$params = [];
        }
    }
}

namespace Joomla\CMS\Cache {
    class Cache
    {
        /** @var list<string> */
        public static array $availableGroups = [];

        /** @var list<string> */
        public static array $cleaned = [];

        public function __construct(array $options = [])
        {
        }

        public function getAll(): array
        {
            $items = [];
            foreach (self::$availableGroups as $group) {
                $items[] = (object) ['group' => $group];
            }

            return $items;
        }

        public function clean(string $group): bool
        {
            self::$cleaned[] = $group;

            return true;
        }

        public static function reset(): void
        {
            self::$availableGroups = [];
            self::$cleaned = [];
        }
    }
}

namespace Joomla\CMS\Event\Cache {
    class AfterPurgeEvent
    {
        public static ?string $lastName = null;

        /** @var array<string, mixed>|null */
        public static ?array $lastArguments = null;

        public function __construct(string $name, array $arguments = [])
        {
            if (array_key_exists('subject', $arguments)) {
                $value = $arguments['subject'];
                if (!\is_string($value) || $value === '') {
                    throw new \TypeError(
                        'AfterPurgeEvent::setSubject(): Argument #1 ($value) must be of type string, '
                        . ($value === '' ? 'empty string' : \get_debug_type($value)) . ' given'
                    );
                }
            }

            self::$lastName = $name;
            self::$lastArguments = $arguments;
        }

        public static function reset(): void
        {
            self::$lastName = null;
            self::$lastArguments = null;
        }
    }
}

namespace Joomla\CMS\Uri {
    class Uri
    {
        public static string $root = 'https://example.test/';

        public static function root(bool $pathonly = false): string
        {
            return self::$root;
        }
    }
}

namespace Joomla\Component\Mcpserver\Tests\Stubs {
    /**
     * Query builder stand-in: records the clauses the service assembles so a
     * test can assert on them, without parsing SQL.
     */
    class StubQuery
    {
        /** @var list<string> */
        public array $clauses = [];

        public function select(string|array $columns): self
        {
            $this->clauses[] = 'select ' . implode(',', (array) $columns);

            return $this;
        }

        public function from(string $table): self
        {
            $this->clauses[] = 'from ' . $table;

            return $this;
        }

        public function where(string $condition): self
        {
            $this->clauses[] = 'where ' . $condition;

            return $this;
        }
    }

    /**
     * Minimal stand-in for Joomla's DatabaseDriver. Tests queue the rows that
     * loadObject() should hand back, in call order.
     */
    class StubDatabase
    {
        /** @var list<object|null> */
        public array $objects = [];

        public ?StubQuery $lastQuery = null;

        public function getQuery(bool $new = false): StubQuery
        {
            return new StubQuery();
        }

        public function quoteName(string|array $name): string|array
        {
            if (\is_array($name)) {
                return array_map(fn(string $one): string => $this->quoteName($one), $name);
            }

            return '`' . $name . '`';
        }

        public function quote(string $text): string
        {
            return "'" . $text . "'";
        }

        public function setQuery(StubQuery $query): self
        {
            $this->lastQuery = $query;

            return $this;
        }

        public function loadObject(): ?object
        {
            return array_shift($this->objects);
        }
    }
}

namespace Joomla\Registry {
    class Registry
    {
        /**
         * @param  array<string, mixed>  $data
         */
        public function __construct(private array $data = [])
        {
        }

        public function get(string $path, mixed $default = null): mixed
        {
            return $this->data[$path] ?? $default;
        }
    }
}
