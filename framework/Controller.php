<?php

namespace Mini;

abstract class Controller
{
    protected function validate(array $rules): array
    {
        $errors = [];
        $data = [];
        foreach ($rules as $field => $ruleString) {
            $value = $_POST[$field] ?? null;
            if (is_string($value)) {
                $value = trim($value);
            }
            $ruleList = explode('|', $ruleString);
            $label = ucwords(str_replace('_', ' ', $field));
            if ($value !== null && !is_scalar($value)) {
                $errors[$field] = t('{field} must be a single value.',['field'=>t($label)]);
                continue;
            }
            if (in_array('required', $ruleList, true) && ($value === null || trim((string) $value) === '')) {
                $errors[$field] = t('{field} is required.',['field'=>t($label)]);
                continue;
            }
            if (($value === null || $value === '') && !in_array('required', $ruleList, true)) {
                $data[$field] = null;
                continue;
            }
            if (in_array('email', $ruleList, true) && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
                $errors[$field] = t('{field} must be a valid email.',['field'=>t($label)]);
            }
            if (in_array('numeric', $ruleList, true) && !is_numeric($value)) {
                $errors[$field] = t('{field} must be a number.',['field'=>t($label)]);
            }
            if (in_array('integer', $ruleList, true) && filter_var($value, FILTER_VALIDATE_INT) === false) {
                $errors[$field] = t('{field} must be a whole number.',['field'=>t($label)]);
            }
            $data[$field] = is_string($value) ? trim($value) : $value;
        }

        if ($errors) {
            $_SESSION['errors'] = $errors;
            $_SESSION['old'] = array_filter($_POST, fn ($value, $key) => is_scalar($value)
                && !in_array($key, ['password', 'password_confirmation', 'current_password', '_token'], true), ARRAY_FILTER_USE_BOTH);
            back();
        }
        return $data;
    }

}
