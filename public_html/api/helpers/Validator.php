<?php

declare(strict_types=1);

class Validator
{
    private array $data;
    private array $errors = [];

    public function __construct(array $data)
    {
        $this->data = $data;
    }

    public static function make(array $data): self
    {
        return new self($data);
    }

    public function required(string ...$fields): self
    {
        foreach ($fields as $field) {
            if (!isset($this->data[$field]) || $this->data[$field] === '' || $this->data[$field] === null) {
                $this->errors[$field][] = ucfirst($field) . ' is required';
            }
        }
        return $this;
    }

    public function email(string $field): self
    {
        if (isset($this->data[$field]) && !filter_var($this->data[$field], FILTER_VALIDATE_EMAIL)) {
            $this->errors[$field][] = 'Invalid email address';
        }
        return $this;
    }

    public function minLength(string $field, int $min): self
    {
        if (isset($this->data[$field]) && strlen($this->data[$field]) < $min) {
            $this->errors[$field][] = ucfirst($field) . " must be at least $min characters";
        }
        return $this;
    }

    public function maxLength(string $field, int $max): self
    {
        if (isset($this->data[$field]) && strlen($this->data[$field]) > $max) {
            $this->errors[$field][] = ucfirst($field) . " must not exceed $max characters";
        }
        return $this;
    }

    public function in(string $field, array $values): self
    {
        if (isset($this->data[$field]) && !in_array($this->data[$field], $values)) {
            $this->errors[$field][] = ucfirst($field) . ' must be one of: ' . implode(', ', $values);
        }
        return $this;
    }

    public function numeric(string $field): self
    {
        if (isset($this->data[$field]) && !is_numeric($this->data[$field])) {
            $this->errors[$field][] = ucfirst($field) . ' must be a number';
        }
        return $this;
    }

    public function integer(string $field): self
    {
        if (isset($this->data[$field]) && !is_int($this->data[$field]) && !ctype_digit((string)$this->data[$field])) {
            $this->errors[$field][] = ucfirst($field) . ' must be an integer';
        }
        return $this;
    }

    public function min(string $field, int|float $min): self
    {
        if (isset($this->data[$field]) && is_numeric($this->data[$field]) && $this->data[$field] < $min) {
            $this->errors[$field][] = ucfirst($field) . " must be at least $min";
        }
        return $this;
    }

    public function max(string $field, int|float $max): self
    {
        if (isset($this->data[$field]) && is_numeric($this->data[$field]) && $this->data[$field] > $max) {
            $this->errors[$field][] = ucfirst($field) . " must not exceed $max";
        }
        return $this;
    }

    public function url(string $field): self
    {
        if (isset($this->data[$field]) && !filter_var($this->data[$field], FILTER_VALIDATE_URL)) {
            $this->errors[$field][] = 'Invalid URL';
        }
        return $this;
    }

    public function date(string $field): self
    {
        if (isset($this->data[$field])) {
            $date = date_parse($this->data[$field]);
            if ($date['error_count'] > 0 || !checkdate($date['month'], $date['day'], $date['year'])) {
                $this->errors[$field][] = 'Invalid date format';
            }
        }
        return $this;
    }

    public function datetime(string $field): self
    {
        if (isset($this->data[$field])) {
            $timestamp = strtotime($this->data[$field]);
            if ($timestamp === false) {
                $this->errors[$field][] = 'Invalid datetime format';
            }
        }
        return $this;
    }

    public function array(string $field): self
    {
        if (isset($this->data[$field]) && !is_array($this->data[$field])) {
            $this->errors[$field][] = ucfirst($field) . ' must be an array';
        }
        return $this;
    }

    public function boolean(string $field): self
    {
        if (isset($this->data[$field]) && !is_bool($this->data[$field]) && !in_array($this->data[$field], [0, 1, '0', '1', 'true', 'false'], true)) {
            $this->errors[$field][] = ucfirst($field) . ' must be a boolean';
        }
        return $this;
    }

    public function unique(string $field, string $table, string $column = null, int $exceptId = null): self
    {
        $column = $column ?? $field;
        if (isset($this->data[$field])) {
            $sql = "SELECT id FROM $table WHERE $column = ?";
            $params = [$this->data[$field]];

            if ($exceptId) {
                $sql .= " AND id != ?";
                $params[] = $exceptId;
            }

            $exists = Database::fetch($sql, $params);
            if ($exists) {
                $this->errors[$field][] = ucfirst($field) . ' already exists';
            }
        }
        return $this;
    }

    public function confirmed(string $field): self
    {
        $confirmField = $field . '_confirmation';
        if (isset($this->data[$field]) && (!isset($this->data[$confirmField]) || $this->data[$field] !== $this->data[$confirmField])) {
            $this->errors[$field][] = ucfirst($field) . ' confirmation does not match';
        }
        return $this;
    }

    public function regex(string $field, string $pattern): self
    {
        if (isset($this->data[$field]) && !preg_match($pattern, $this->data[$field])) {
            $this->errors[$field][] = ucfirst($field) . ' format is invalid';
        }
        return $this;
    }

    public function fails(): bool
    {
        return !empty($this->errors);
    }

    public function passes(): bool
    {
        return empty($this->errors);
    }

    public function errors(): array
    {
        return $this->errors;
    }

    public function validate(): void
    {
        if ($this->fails()) {
            Response::validationError($this->errors);
        }
    }

    public function get(string $field, mixed $default = null): mixed
    {
        return $this->data[$field] ?? $default;
    }

    public function all(): array
    {
        return $this->data;
    }

    public function only(string ...$fields): array
    {
        return array_intersect_key($this->data, array_flip($fields));
    }
}
