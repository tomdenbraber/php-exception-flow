<?php
namespace PhpExceptionFlow;

use PhpParser\Node\Stmt\Catch_;
use PHPTypes\Type;
use PHPTypes\State;

class GuardedScope {
	/** @var $enclosing_scope Scope: the scope that encloses this guarded scope */
	private $enclosing_scope;
	/** @var $inclosed_scope Scope: the scope that is enclosed by this guarded scope */
	private $inclosed_scope;
	/** @var $catch_clauses Catch_[] */
	private $catch_clauses;

	/** @var \SplObjectStorage */
	private $caught_exceptions;

	/** @var Type[] */
	private $unchaught = [];

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
	 * @return Scope that is inclosed by this guarded scope
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
	 * @return string[]
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
	 * @param State $state
	 */
	public function determineCaughtExceptionTypes(State $state) {
		$already_caught = array();
		foreach ($this->catch_clauses as $catch_clause) {
			$exc_types = $this->determineCaughtExceptionTypesForCatch($catch_clause, $state);
			$caught_by_clause = array_diff($exc_types, $already_caught);
			$this->caught_exceptions[$catch_clause] = $caught_by_clause;
			$already_caught = array_merge($exc_types, $already_caught);
		}
	}

	/**
	 * @param Catch_ $catch
	 * @param State $state
	 * @return string[]
	 */
	private function determineCaughtExceptionTypesForCatch(Catch_ $catch, State $state) {
		$caught_type = strtolower(implode('\\', $catch->type->parts));
		$resolved_by_list = [];
		foreach ($state->classResolvedBy[$caught_type] as $resolved_by) {
			if (isset($state->classLookup[$resolved_by]) === true) {
				$resolved_by_list[] = new Type(Type::TYPE_OBJECT, $state->classLookup[$resolved_by]->name->value);
			} else {
				$resolved_by_list[] = new Type(Type::TYPE_OBJECT, [], $resolved_by);
			}
		}

		return array_unique($resolved_by_list);
	}


	/**
	 * @param State $state
	 * @param bool $determine_caught; if determineCaughtExceptions was not called before, set this to true
	 */
	public function determineUncaughtExceptions(State $state, $determine_caught = true) {
		if ($determine_caught === true) {
			$this->determineCaughtExceptionTypes($state);
		}

		$resolves = $state->classResolves;
		$exceptions_to_be_caught = $this->inclosed_scope->getEncounters();
		$uncaught = array();
		foreach ($exceptions_to_be_caught as $exception) {
			$caught = false;
			$current_type_str = strtolower($exception->userType);
			foreach ($this->catch_clauses as $catch_clause) {
				$current_catches = $this->caught_exceptions[$catch_clause];
				foreach ($current_catches as $current_catch) {
					$current_catch_str = $current_catch->userType;
					if (isset($resolves[$current_catch_str][$current_type_str]) === true) {
						$caught = true;
						break;
					}
				}
			}
			if ($caught === false) {
				$uncaught[] = $exception;
			}
		}
		$this->unchaught = $uncaught;
	}

	public function getUncaught() {
		return $this->unchaught;
	}
}