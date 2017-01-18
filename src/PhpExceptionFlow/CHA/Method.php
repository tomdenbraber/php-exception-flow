<?php
namespace PhpExceptionFlow\CHA;

class Method {
	/** @var string $class*/
	private $class;
	/** @var string $name */
	private $name;
	/** @var array $args  */
	private $args;

	public function __construct($class, $name, $args) {
		$this->class = $class;
		$this->name = $name;
		$this->args = $args;
	}

	public function getClass() {
		return $this->class;
	}

	public function getName() {
		return $this->name;
	}
}