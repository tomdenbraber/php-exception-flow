<?php
namespace PhpExceptionFlow\Path;

use PhpExceptionFlow\Scope\Scope;
use PhpParser\Node\Stmt\Catch_;

class Catches extends AbstractPathEntry {
	/** @var Scope $encountered_in_scope */
	private $encountered_in_scope;

	/** @var Catch_ $caught_by the catch statement that caught this statement */
	private $caught_by;


	public function __construct(Scope $encountered_in_scope, Catch_ $catch_statement) {
		$this->encountered_in_scope = $encountered_in_scope;
		$this->caught_by = $catch_statement;
	}

	public function getFromScope() {
		return $this->encountered_in_scope;
	}

	public function getToScope() {
		return $this->encountered_in_scope;
	}

	public function getCaughtBy() {
		return $this->caught_by;
	}

	/**
	 * A Catches is always the last entry in a chain, as after an exception is caught, it cannot be propagated further
	 * @return bool
	 */
	public function isLastEntry() {
		return true;
	}

	public function getType() {
		return "catches";
	}

	public function __toString() {
		return $this->getType() . ":" . $this->getFromScope()->getName();
	}
}