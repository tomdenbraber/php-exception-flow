<?php
namespace PhpExceptionFlow\Path;

use PhpExceptionFlow\Scope\GuardedScope;
use PhpExceptionFlow\Scope\Scope;
use PhpParser\Node\Stmt\Catch_;

class Catches extends AbstractPathEntry {
	/** @var GuardedScope $guarded_scope */
	private $guarded_scope;

	/** @var Catch_ $caught_by the catch statement that caught this statement */
	private $caught_by;


	public function __construct(GuardedScope $caught_in_guarded_scope, Catch_ $catch_statement) {
		$this->guarded_scope = $caught_in_guarded_scope;
		$this->caught_by = $catch_statement;
	}

	public function getFromScope() {
		return $this->guarded_scope->getInclosedScope();
	}

	public function getToScope() {
		return $this->guarded_scope->getInclosedScope();
	}

	public function getCaughtBy() {
		return $this->caught_by;
	}

	/**
	 * @return \PhpExceptionFlow\Scope\GuardedScope
	 */
	public function getGuardedScope() {
		return $this->guarded_scope;
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