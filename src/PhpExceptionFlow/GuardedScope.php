<?php
namespace PhpExceptionFlow;

use PhpParser\Node\Stmt\Catch_;
use PHPTypes\Type;

class GuardedScope {
	/** @var $enclosing_scope Scope: the scope that encloses this guarded scope */
	private $enclosing_scope;
	/** @var $inclosed_scope Scope: the scope that is enclosed by this guarded scope */
	private $inclosed_scope;
	/** @var $catch_clauses Catch_[] */
	private $catch_clauses;
	/** @var $caught_exceptions \SplObjectStorage */
	private $caught_exceptions;

	/**
	 * GuardedScope constructor.
	 * @param Scope $inclosed
	 * @param Scope $enclosing
	 * @param Catch_[] $catch_clauses
	 */
	public function __construct(Scope $enclosing, Scope $inclosed, $catch_clauses = array()) {
		$this->enclosing_scope = $enclosing;
		$this->inclosed_scope = $inclosed;
		$this->catch_clauses = array();
		foreach ($catch_clauses as $catch_clause) {
			$this->addCatchClause($catch_clause);
		}

		$this->caught_exceptions = new \SplObjectStorage();
	}

	/**
	 * @param Catch_ $catch_clause
	 */
	public function addCatchClause(Catch_ $catch_clause) {
		$this->catch_clauses[] = $catch_clause;
	}

	/**
	 * @return Scope that encloses this guarded scope
	 */
	public function getEnclosingScope() {
		return $this->enclosing_scope;
	}

	/**
	 * @return SCope that is inclosed by this guarded scope
	 */
	public function getInclosedScope() {
		return $this->inclosed_scope;
	}

	/**
	 * @param Scope $scope
	 */
	public function setInclosedScope(Scope $scope) {
		$this->inclosed_scope = $scope;
	}

	/**
	 * @return Catch_[]
	 */
	public function getCatchClauses() {
		return $this->catch_clauses;
	}

	/**
	 * @param Catch_ $catch_clause
	 * @return Type
	 * @throws \LogicException
	 */
	public function getCaughtExceptionsForClause(Catch_ $catch_clause) {
		if ($this->caught_exceptions->contains($catch_clause) === true) {
			return $this->caught_exceptions[$catch_clause];
		} else {
			throw new \LogicException("You're trying to fetch a catch clause which does not belong to this guarded scope");
		}
	}

	/**
	 * Determines for each catch clause which exception types are caught
	 */
	public function determineCaughtExceptionTypes() {
		foreach ($this->catch_clauses as $catch_clause) {
			$this->caught_exceptions[$catch_clause] = $this->determineCaughtExceptionType($catch_clause);
		}
	}

	private function determineCaughtExceptionType(Catch_ $catch) {
		return new Type(Type::TYPE_OBJECT, array(), implode('\\', $catch->type->parts));
	}
}