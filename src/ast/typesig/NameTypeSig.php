<?php
/**
 * Quack Compiler and toolkit
 * Copyright (C) 2015-2017 Quack and CONTRIBUTORS
 *
 * This file is part of Quack.
 *
 * Quack is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * Quack is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with Quack.  If not, see <http://www.gnu.org/licenses/>.
 */
namespace QuackCompiler\Ast\TypeSig;

use \QuackCompiler\Ast\TypeSig;
use \QuackCompiler\Parser\Parser;
use \QuackCompiler\Pretty\Parenthesized;

class NameTypeSig implements TypeSig
{
    use Parenthesized;

    public $name;
    public $values;

    public function __construct($name, $values)
    {
        $this->name = $name;
        $this->values = $values;
    }

    public function format(Parser $parser)
    {
        $source = $this->name;

        if (count($this->values) > 0) {
            $source .= implode(', ', array_map(function ($value) use ($parser) {
                return $value->format($parser);
            }));
        }

        return $this->parenthesize($source);
    }
}
