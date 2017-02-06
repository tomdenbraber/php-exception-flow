<?php

namespace PhpExceptionFlow\AstBridge;

use PhpParser\NodeTraverser as AstTraverser;
use PhpParser\NodeVisitor as AstNodeVisitor;

class SystemTraverser implements SystemTraverserInterface {
	/** @var AstTraverser */
	private $traverser;

	public function __construct(AstTraverser $traverser) {
		$this->traverser = $traverser;
	}

	public function addVisitor(AstNodeVisitor $visitor) {
		$this->traverser->addVisitor($visitor);
	}

	public function traverse(System $ast_system) {
		foreach ($ast_system->getFilenames() as $filename) {
			$this->traverser->traverse($ast_system->getAst($filename));
		}
	}
}