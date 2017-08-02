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

use \QuackCompiler\Lexer\Tag;
use \QuackCompiler\Lexer\Token;

use \QuackCompiler\Ast\Stmt\ClassStmt;
use \QuackCompiler\Ast\Stmt\EnumStmt;
use \QuackCompiler\Ast\Stmt\FnStmt;
use \QuackCompiler\Ast\Stmt\FnSignatureStmt;
use \QuackCompiler\Ast\Stmt\ImplStmt;
use \QuackCompiler\Ast\Stmt\ImportStmt;
use \QuackCompiler\Ast\Stmt\OpenStmt;
use \QuackCompiler\Ast\Stmt\ShapeStmt;
use \QuackCompiler\Ast\Stmt\StmtList;

class DeclParser
{
    use Attachable;

    public $reader;

    public function __construct($reader)
    {
        $this->reader = $reader;
    }

    public function _classStmt()
    {
        $this->reader->match(Tag::T_CLASS);
        $name = $this->name_parser->_identifier();
        $body = [];

        while (0 !== $this->reader->lookahead->getTag() && !$this->reader->is(Tag::T_END)) {
            $body[] = $this->_fnSignature();
        }

        $this->reader->match(Tag::T_END);

        return new ClassStmt($name, $body);
    }

    public function _enumStmt()
    {
        $this->reader->match(Tag::T_ENUM);
        $entries = [];
        $name = $this->name_parser->_identifier();

        while ($this->reader->is(Tag::T_IDENT)) {
            $entries[] = $this->name_parser->_identifier();
        }

        $this->reader->match(Tag::T_END);

        return new EnumStmt($name, $entries);
    }

    public function _fnSignature()
    {
        $name = null;
        $parameters = [];
        $type = null;
        $name = $this->name_parser->_identifier();

        $this->reader->match('(');

        if (!$this->reader->consumeIf(')')) {
            do {
                $parameters[] = $this->stmt_parser->_parameter();
            } while ($this->reader->consumeIf(','));
            $this->reader->match(')');
        }

        if ($this->reader->consumeIf('->')) {
            $type = $this->type_parser->_type();
        }

        return new FnSignatureStmt($name, $parameters, $type);
    }

    public function _fnStmt($is_method = false)
    {
        $is_export = false;
        $is_short = false;
        $body = null;

        if ($is_export = $this->reader->is(Tag::T_EXPORT)) {
            $this->reader->consume(); // export
        }

        if (!$is_method) {
            $this->reader->match(Tag::T_FN);
        }
        $signature = $this->_fnSignature();

        // Is short method?
        if ($is_short = $this->reader->is(':-')) {
            $this->reader->consume(); // :-
            $body = $this->expr_parser->_expr();
        } else {
            $body = iterator_to_array($this->stmt_parser->_innerStmtList());
            $this->reader->match(Tag::T_END);
        }

        return new FnStmt($signature, $body, $is_method, $is_short, $is_export);
    }

    public function _implStmt()
    {
        // Shapes are for properties
        // Classes are for methods
        $type = Tag::T_SHAPE;
        $this->reader->match(Tag::T_IMPL);
        $class_or_shape = $this->name_parser->_qualifiedName();
        $class_for = null;
        // When it contains "for", it is being applied for a class
        if ($this->reader->is(Tag::T_FOR)) {
            $type = Tag::T_CLASS;
            $this->reader->consume();
            $class_for = $this->name_parser->_qualifiedName();
        }

        $body = new StmtList(iterator_to_array($this->_implStmtList()));
        $this->reader->match(Tag::T_END);

        return new ImplStmt($type, $class_or_shape, $class_for, $body);
    }

    public function _implStmtList()
    {
        while ($this->reader->is(Tag::T_IDENT)) {
            yield $this->_fnStmt(/* implicit */ true);
        }
    }

    public function _importStmt()
    {
        $this->reader->match(Tag::T_IMPORT);
        return new ImportStmt($this->name_parser->_qualifiedName());
    }

    public function _shapeStmt()
    {
        $this->reader->match(Tag::T_SHAPE);
        $name = $this->name_parser->_identifier();
        $members = [];

        while ($this->reader->is(Tag::T_IDENT)) {
            $members[] = $this->name_parser->_identifier();
            // TODO: Bind type for member
            $this->reader->match('::');
            $type = $this->type_parser->_type();
        }

        $this->reader->match(Tag::T_END);

        return new ShapeStmt($name, $members);
    }
}
