<?php

namespace PhpExceptionFlow\AstBridge;

use PhpParser\NodeVisitor;

interface SystemTraverserInterface {
	public function traverse(System $ast_system);

	public function addVisitor(NodeVisitor $visitor);
}