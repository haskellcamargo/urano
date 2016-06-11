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
namespace QuackCompiler\Ast\Stmt;

use \QuackCompiler\Parser\Parser;

class ClassStmt implements Stmt
{
  public $category;
  public $name;
  public $extends;
  public $implements;
  public $body;

  public function __construct($category, $name, $extends, $implements, $body)
  {
    $this->category = $category;
    $this->name = $name;
    $this->extends = $extends;
    $this->implements = $implements;
    $this->body = $body;
  }

  public function format(Parser $parser)
  {
    throw new \Exception('TODO');
  }
}
