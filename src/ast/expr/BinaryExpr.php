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
namespace QuackCompiler\Ast\Expr;

use \QuackCompiler\Ast\Expr;
use \QuackCompiler\Ast\Node;
use \QuackCompiler\Ds\Set;
use \QuackCompiler\Intl\Localization;
use \QuackCompiler\Lexer\Tag;
use \QuackCompiler\Parser\Parser;
use \QuackCompiler\Pretty\Parenthesized;
use \QuackCompiler\Scope\Scope;
use \QuackCompiler\Scope\ScopeError;
use \QuackCompiler\Scope\Symbol;
use \QuackCompiler\Types\HindleyMilner;
use \QuackCompiler\Types\TypeError;

class BinaryExpr extends Node implements Expr
{
    use Parenthesized;

    public $left;
    public $operator;
    public $right;

    public function __construct(Expr $left, $operator, $right)
    {
        $this->left = $left;
        $this->operator = $operator;
        $this->right = $right;
    }

    public function format(Parser $parser)
    {
        $source = $this->left->format($parser);
        $source .= ' ';
        $source .= Tag::getOperatorLexeme($this->operator);
        $source .= ' ';
        $source .= $this->right->format($parser);

        return $this->parenthesize($source);
    }

    public function injectScope($parent_scope)
    {
        $this->scope = $parent_scope;
        $this->left->injectScope($parent_scope);
        $this->right->injectScope($parent_scope);

        if (':-' === $this->operator) {
            if ($this->left instanceof NameExpr) {
                $symbol = $parent_scope->lookup($this->left->name);

                // When symbol is not a variable
                if (~$symbol & Symbol::S_VARIABLE) {
                    throw new ScopeError(Localization::message('SCO070', [$this->left->name]));
                }

                // When symbol is not mutable
                if (~$symbol & Symbol::S_MUTABLE) {
                    throw new ScopeError(Localization::message('SCO080', [$this->left->name]));
                }
            } else {
                // We have a range of specific nodes that are allowed
                $valid_assignment = $this->left instanceof AccessExpr ||
                    $this->left instanceof ListExpr; // List destructuring

                if (!$valid_assignment) {
                    throw new ScopeError(Localization::message('SCO090', []));
                }

                // When it is array destructuring, ensure all the subnodes are names
                // TODO: Implement destructuring on let, because this is currently useless
                if ($this->left instanceof ListExpr) {
                    foreach ($this->left->items as $item) {
                        if (!($item instanceof NameExpr)) {
                            throw new ScopeError(Localization::message('SCO100', []));
                        }
                    }
                }
            }
        }
    }

    public function analyze(Scope $scope, Set $non_generic)
    {
        $op_name = Tag::getOperatorLexeme($this->operator);
        $native_number = $scope->getPrimitiveType('Number');
        $native_string = $scope->getPrimitiveType('String');

        $left_type = $this->left->analyze($scope, $non_generic);
        $right_type = $this->right->analyze($scope, $non_generic);

        $numeric_op = ['+', '-', '*', '**', '/', '>>', '<<', Tag::T_MOD];
        if (in_array($this->operator, $numeric_op, true)) {
            try {
                HindleyMilner::unify($left_type, $native_string);
                HindleyMilner::unify($right_type, $native_string);
                return $native_string;
            } catch (TypeError $error) {
                try {
                    HindleyMilner::unify($left_type, $native_number);
                    HindleyMilner::unify($right_type, $native_number);
                    return $native_number;
                } catch (TypeError $error) {
                    throw new TypeError(
                        Localization::message('TYP110', [$op_name, $left_type, $op_name, $right_type])
                    );
                }
            }
        }
    }

    public function getType()
    {
        $bool = $this->scope->getPrimitiveType('Bool');
        $type = (object) [
            'left'  => $this->left->getType(),
            'right' => 'string' === gettype($this->right) ? $this->right : $this->right->getType()
        ];

        $op_name = Tag::getOperatorLexeme($this->operator);

        // Type-checking for assignment. Don't worry. Left-hand assignment was handled on
        // scope injection
        if (':-' === $this->operator) {
            // When right side cannot be attributed to left side
            if (!$type->left->check($type->right)) {
                $target = $this->left instanceof NameExpr
                    ? "`{$this->left->name}' :: {$type->left}"
                    : $type->right;

                throw new TypeError(Localization::message('TYP100', [$type->right, $target]));
            }

            // The return type is an effect informing about the mutability
            $mutability = $this->scope->getPrimitiveType('Mutability');
            $mutability->parameters = [$type->left];
            return $mutability;
        }

        // Type checking for equality operators
        $eq_op = ['=', '<>', '>', '>=', '<', '<='];
        if (in_array($this->operator, $eq_op, true)) {
            if (!$type->left->check($type->right)) {
                throw new TypeError(Localization::message('TYP130', [$type->left, $op_name, $type->right]));
            }

            return $bool;
        }

        // Type checking for string matched by regex
        if ('=~' === $this->operator) {
            if (!$type->left->isString() || !$type->right->isRegex()) {
                throw new TypeError(Localization::message('TYP110', [$op_name, $type->left, $op_name, $type->right]));
            }

            return $bool;
        }

        // Boolean algebra and bitwise operations
        $bool_op = [Tag::T_AND, Tag::T_OR, Tag::T_XOR];
        if (in_array($this->operator, $bool_op, true)) {
            if ($bool->check($type->left) && $bool->check($type->right)) {
                return $bool;
            }

            if ($type->left->isNumber() && $type->right->isNumber()) {
                return $this->scope->getPrimitiveType('Number');
            }

            throw new TypeError(Localization::message('TYP110', [$op_name, $type->left, $op_name, $type->right]));
        }
    }
}
