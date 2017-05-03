<?php
namespace PhpExceptionFlow\FlowCalculator;

use PhpExceptionFlow\Exception_;
use PhpExceptionFlow\Scope\GuardedScope;
use PhpExceptionFlow\Scope\Scope;
use PhpExceptionFlow\Scope\ScopeVisitor\CaughtExceptionTypesCalculator;
use PhpParser\Node\Stmt\Catch_;
use PHPTypes\Type;

class UncaughtCalculator extends AbstractMutableFlowCalculator {

	/** @var Type[][]|\SplObjectStorage */
	private $guarded_scopes;

	/**
	 * @var \SplObjectStorage $catch_clause_catches
	 * this contains actual instances of exceptions that are caught by the catch clause, not the types that it resolves
	 */
	private $catch_clause_catches;

	/**
	 * @var \SplObjectStorage $catch_clause_potentially_catches
	 */
	private $catch_clause_potentially_catches;

	/** @var CaughtExceptionTypesCalculator $catch_clause_type_resolver */
	private $catch_clause_type_resolver;

	/** @var CombiningCalculator */
	private $encounters_calculator;

	public function __construct(CaughtExceptionTypesCalculator $catch_clause_type_resolver, CombiningCalculator $encounters_calculator) {
		parent::__construct();

		$this->guarded_scopes = new \SplObjectStorage;
		$this->catch_clause_catches = new \SplObjectStorage;
		$this->catch_clause_potentially_catches = new \SplObjectStorage;
		$this->catch_clause_type_resolver = $catch_clause_type_resolver;
		$this->encounters_calculator = $encounters_calculator;
	}

	public function determineForScope(Scope $scope) {
		$guarded_scopes = $scope->getGuardedScopes();
		$uncaught = array();
		foreach ($guarded_scopes as $guarded_scope) {
			$inclosed_encounters = $this->encounters_calculator->getForScope($guarded_scope->getInclosedScope());
			$catch_clauses = $guarded_scope->getCatchClauses();
			foreach ($catch_clauses as $catch_clause) {
				$potentially_caught_types = $this->catch_clause_type_resolver->getCaughtTypesForClause($catch_clause);
				$this->catch_clause_potentially_catches[$catch_clause] = $potentially_caught_types;

				$actually_caught_exceptions = [];
				foreach ($inclosed_encounters as $exception) {
					foreach ($potentially_caught_types as $potentially_caught_type) {
						if ($potentially_caught_type->equals($exception->getType()) === true) {
							$actually_caught_exceptions[] = $exception;
							$exception->catches($guarded_scope, $catch_clause);
							//remove this exception from the exceptions that still need catching
							unset($inclosed_encounters[array_search($exception, $inclosed_encounters, true)]);
						}
					}
				}
				$this->catch_clause_catches[$catch_clause] = $actually_caught_exceptions;
			}
			$uncaught = array_merge($uncaught, $inclosed_encounters);
			$this->guarded_scopes[$guarded_scope] = $uncaught;
			foreach ($inclosed_encounters as $exception_) {
				$exception_->uncaught($guarded_scope, $scope);
			}
		}

		$this->setScopeHasChanged($scope, $uncaught);
		$this->scopes[$scope] = $uncaught;
	}

	public function getCaughtExceptions(Catch_ $catch_clause) {
		if ($this->catch_clause_catches->contains($catch_clause) === false) {
			throw new \UnexpectedValueException("Given catch clause could not be found in this set.");
		}
		return $this->catch_clause_catches[$catch_clause];
	}

	public function getPotentiallyCaughtTypes(Catch_ $catch_clause) {
		if ($this->catch_clause_potentially_catches->contains($catch_clause) === false) {
			throw new \UnexpectedValueException("Given catch clause could not be found in this set.");
		}
		return $this->catch_clause_potentially_catches[$catch_clause];
	}

	/**
	 * @param GuardedScope $guarded_scope
	 * @return Type[] - uncaught types for the
	 * @throws \UnexpectedValueException
	 */
	public function getForGuardedScope(GuardedScope $guarded_scope) {
		if ($this->guarded_scopes->contains($guarded_scope) === false) {
			throw new \UnexpectedValueException(sprintf("Given guarded scope (enclosed by scope with name %s) could not be found in this set.", $guarded_scope->getEnclosingScope()->getName()));
		}
		return $this->guarded_scopes[$guarded_scope];
	}

	public function getType() {
		return "uncaught";
	}
}