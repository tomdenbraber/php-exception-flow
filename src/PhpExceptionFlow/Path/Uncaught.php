<?php
namespace PhpExceptionFlow\Path;

use PhpExceptionFlow\Scope\GuardedScope;
use PhpExceptionFlow\Scope\Scope;

class Uncaught extends AbstractPathEntry {
	/** @var GuardedScope $escaped_scope */
	private $escaped_scope;
	/** @var Scope $to_scope */
	private $to_scope;

	public function __construct(GuardedScope $escaped_scope, Scope $to_scope) {
		$this->to_scope = $to_scope;
		$this->escaped_scope = $escaped_scope;
	}

	public function getToScope() {
		return $this->to_scope;
	}

	public function getFromScope() {
		return $this->escaped_scope->getInclosedScope();
	}

	public function getType() {
		return "uncaught";
	}

	/**
	 * @return GuardedScope
	 */
	public function getEscapedScope() {
		return $this->escaped_scope;
	}

	public function equals(PathEntryInterface $path_entry) {
		return parent::equals($path_entry) &&
			$path_entry instanceof Uncaught &&
			$this->escaped_scope = $path_entry->getEscapedScope();
	}

	public function __toString() {
		return parent::__toString() . "(" . $this->escaped_scope->getInclosedScope()->getName() . ")";
	}
}