<?php

/**
 * Attribute data type interface
 *
 * Copyright @ WereWolf Labs OÜ.
 */

namespace Framework\Database\DataTypes;

interface DataTypeInterface {
    public function dataType(): string ;

    public function dataLength(): null|int;

    public function defaultValue(): null|string|int|float|bool;

    public function notNull(): bool;
}