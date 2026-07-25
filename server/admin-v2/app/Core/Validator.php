<?php
/**
 * Server-side request validation. Rules are a compact string DSL per field, e.g.
 *   'price' => 'required|int|min:1|max:100000'
 * Collects errors; the controller redirects back with them on failure.
 */

namespace App\Core;

final class Validator
{
    private array $errors = [];
    private array $data;

    public function __construct(array $data)
    {
        $this->data = $data;
    }

    public static function make(array $data): self
    {
        return new self($data);
    }

    public function validate(array $rules): bool
    {
        foreach ($rules as $field => $ruleset) {
            $value = $this->data[$field] ?? null;
            foreach (explode('|', $ruleset) as $rule) {
                if ($rule === '') {
                    continue;
                }
                [$name, $arg] = array_pad(explode(':', $rule, 2), 2, null);
                $this->apply($field, $value, $name, $arg);
            }
        }
        return !$this->fails();
    }

    private function apply(string $field, $value, string $rule, ?string $arg): void
    {
        $str = is_string($value) ? trim($value) : $value;
        switch ($rule) {
            case 'required':
                if ($str === null || $str === '' || $str === []) {
                    $this->add($field, 'This field is required.');
                }
                break;
            case 'int':
                if ($str !== null && $str !== '' && filter_var($str, FILTER_VALIDATE_INT) === false) {
                    $this->add($field, 'Must be a whole number.');
                }
                break;
            case 'numeric':
                if ($str !== null && $str !== '' && !is_numeric($str)) {
                    $this->add($field, 'Must be a number.');
                }
                break;
            case 'min':
                if (is_numeric($str) && (float) $str < (float) $arg) {
                    $this->add($field, 'Must be at least ' . $arg . '.');
                } elseif (is_string($str) && !is_numeric($str) && mb_strlen($str) < (int) $arg) {
                    $this->add($field, 'Must be at least ' . $arg . ' characters.');
                }
                break;
            case 'max':
                if (is_numeric($str) && (float) $str > (float) $arg) {
                    $this->add($field, 'Must be at most ' . $arg . '.');
                } elseif (is_string($str) && !is_numeric($str) && mb_strlen($str) > (int) $arg) {
                    $this->add($field, 'Must be at most ' . $arg . ' characters.');
                }
                break;
            case 'minlen':
                // Always a character-length check, even for numeric-looking strings
                // (Till/Paybill/phone numbers). Empty optional values pass.
                if ($str !== null && $str !== '' && mb_strlen((string) $str) < (int) $arg) {
                    $this->add($field, 'Must be at least ' . $arg . ' characters.');
                }
                break;
            case 'maxlen':
                // Always a character-length check, even for numeric-looking strings.
                if ($str !== null && $str !== '' && mb_strlen((string) $str) > (int) $arg) {
                    $this->add($field, 'Must be at most ' . $arg . ' characters.');
                }
                break;
            case 'in':
                $allowed = explode(',', (string) $arg);
                if ($str !== null && $str !== '' && !in_array((string) $str, $allowed, true)) {
                    $this->add($field, 'Invalid selection.');
                }
                break;
            case 'regex':
                if ($str !== null && $str !== '' && !preg_match('/' . str_replace('/', '\/', (string) $arg) . '/', (string) $str)) {
                    $this->add($field, 'Invalid format.');
                }
                break;
            case 'slug':
                if ($str !== null && $str !== '' && !preg_match('/^[a-z0-9][a-z0-9_\-]*$/', (string) $str)) {
                    $this->add($field, 'Use lowercase letters, numbers, hyphen or underscore.');
                }
                break;
            case 'msisdn':
                if ($str !== null && $str !== '' && !preg_match('/^(?:0[17]\d{8}|254[17]\d{8}|\d{5,7})$/', preg_replace('/\s/', '', (string) $str))) {
                    $this->add($field, 'Enter a valid Kenyan number or shortcode.');
                }
                break;
            case 'email':
                if ($str !== null && $str !== '' && !filter_var($str, FILTER_VALIDATE_EMAIL)) {
                    $this->add($field, 'Enter a valid email.');
                }
                break;
        }
    }

    public function add(string $field, string $message): void
    {
        $this->errors[$field][] = $message;
    }

    public function fails(): bool
    {
        return !empty($this->errors);
    }

    public function errors(): array
    {
        return $this->errors;
    }

    public function firstErrors(): array
    {
        $flat = [];
        foreach ($this->errors as $field => $msgs) {
            $flat[$field] = $msgs[0];
        }
        return $flat;
    }
}
