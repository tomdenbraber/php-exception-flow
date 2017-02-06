<?php
namespace PhpExceptionFlow\CallGraphConstruction;

use PhpParser\Node\Stmt\ClassMethod;

class Method {
	/** @var string $class */
	private $class;
	/** @var ClassMethod $method */
	private $method_node;


	public function __construct($class, ClassMethod $method) {
		$this->class = $class;
		$this->method_node = $method;
	}

	public function getClass() {
		return $this->class;
	}

	public function getName() {
		return $this->method_node->name;
	}
}