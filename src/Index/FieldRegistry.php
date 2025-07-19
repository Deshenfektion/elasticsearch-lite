<?php

declare(strict_types=1);

namespace EsLite\Index;

use EsLite\Exception\ConfigurationException;
use EsLite\Support\Config;

final class FieldRegistry
{
    private array $idsByName = [];

    private array $namesById = [];

    private array $boosts = [];

    public function __construct(array $fields)
    {
        foreach ($fields as $name => $definition) {
            $id = (int) ($definition['id'] ?? 0);
            $boost = (float) ($definition['boost'] ?? 1.0);

            if ($id < 1 || $id > 255) {
                throw new ConfigurationException(sprintf('Field "%s" needs an id between 1 and 255.', $name));
            }

            if (isset($this->namesById[$id])) {
                throw new ConfigurationException(sprintf(
                    'Field id %d is used by both "%s" and "%s".',
                    $id,
                    $this->namesById[$id],
                    $name,
                ));
            }

            $this->idsByName[$name] = $id;
            $this->namesById[$id] = $name;
            $this->boosts[$name] = $boost;
        }

        if ($this->idsByName === []) {
            throw new ConfigurationException('At least one searchable field must be configured.');
        }
    }

    public static function fromConfig(Config $config): self
    {
        return new self($config->array('app.fields'));
    }

    public static function default(): self
    {
        return new self([
            'title' => ['id' => 1, 'boost' => 3.0],
            'tags' => ['id' => 2, 'boost' => 2.0],
            'body' => ['id' => 3, 'boost' => 1.0],
        ]);
    }

    public function has(string $name): bool
    {
        return isset($this->idsByName[$name]);
    }

    public function id(string $name): int
    {
        return $this->idsByName[$name]
            ?? throw ConfigurationException::unknown('field', $name, $this->names());
    }

    public function name(int $id): string
    {
        return $this->namesById[$id]
            ?? throw ConfigurationException::unknown('field id', (string) $id, array_map(strval(...), $this->ids()));
    }

    public function boost(string $name): float
    {
        return $this->boosts[$name] ?? 1.0;
    }

    public function boostById(int $id): float
    {
        return $this->boost($this->name($id));
    }

    public function names(): array
    {
        return array_keys($this->idsByName);
    }

    public function ids(): array
    {
        return array_values($this->idsByName);
    }

    public function count(): int
    {
        return count($this->idsByName);
    }

    public function toArray(): array
    {
        $fields = [];

        foreach ($this->idsByName as $name => $id) {
            $fields[$name] = ['id' => $id, 'boost' => $this->boosts[$name]];
        }

        return $fields;
    }
}
