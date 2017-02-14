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

	public function isImplemented() {
		return $this->method_node->isAbstract() === false && is_array($this->method_node->getStmts()) === true;
	}

	public function isPrivate() {
		return $this->method_node->isPrivate();
	}

	public function __toString() {
		return sprintf("%s.%s(%d) (%d)", $this->class, $this->method_node->name, count($this->method_node->params), $this->method_node->type);
	}
}