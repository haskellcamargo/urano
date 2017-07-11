<?php
/**
 * Quack Compiler and toolkit
 * Copyright (C) 2016 Marcelo Camargo <marcelocamargo@linuxmail.org> and
 * CONTRIBUTORS.
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
namespace QuackCompiler\Parser;

class Grammar
{
    private $main;

    public function __construct(TokenReader $parser)
    {
        $name_parser = new NameParser($parser);
        $type_parser = new TypeParser($parser);
        $expr_parser = new ExprParser($parser);
        $stmt_parser = new StmtParser($parser);

        $type_parser->attachParsers([
            'name_parser' => $name_parser
        ]);
        $expr_parser->attachParsers([
            'name_parser' => $name_parser,
            'stmt_parser' => $stmt_parser
        ]);
        $stmt_parser->attachParsers([
            'name_parser' => $name_parser,
            'type_parser' => $type_parser,
            'expr_parser' => $expr_parser
        ]);

        $this->main = $stmt_parser;
    }

    public function start()
    {
        return $this->main->_program();
    }
}
