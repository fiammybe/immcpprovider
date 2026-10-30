<?php

declare(strict_types=1);

namespace ImMcpProvider\Ipf;

use icms;
use icms_db_criteria_Item;
use RuntimeException;

final class SafeCriteriaItem extends icms_db_criteria_Item
{
    public function __construct(private readonly string $field, private readonly mixed $value)
    {
        parent::__construct($field, $value);
    }

    public function render(): string
    {
        if ($this->value === null) {
            return $this->field . ' IS NULL';
        }

        $quoted = icms::$xoopsDB->quoteString((string) $this->value);

        if (! is_string($quoted) || $quoted === '') {
            throw new RuntimeException('Failed to quote filter value.');
        }

        return $this->field . ' = ' . $quoted;
    }
}
