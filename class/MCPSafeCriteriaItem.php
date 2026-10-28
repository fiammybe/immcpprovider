<?php
declare(strict_types=1);

class MCPSafeCriteriaItem extends icms_db_criteria_Item
{
    public function __construct(private string $field, private mixed $value)
    {
        parent::__construct($field, $value);
    }

    public function render(): string
    {
        if ($this->value === null) {
            return $this->field . ' IS NULL';
        }

        $quoted = icms::$xoopsDB->quoteString((string) $this->value);
        if (!is_string($quoted) || $quoted === '') {
            throw new RuntimeException('Failed to quote filter value.');
        }
        return $this->field . ' = ' . $quoted;
    }
}
