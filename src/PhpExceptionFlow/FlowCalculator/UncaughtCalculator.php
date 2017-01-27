<?php
namespace PhpExceptionFlow\FlowCalculator;

use PhpExceptionFlow\EncountersCalculator;
use PhpExceptionFlow\GuardedScope;
use PhpExceptionFlow\Scope;
use PhpParser\Node\Stmt\Catch_;
use PHPTypes\State;
use PHPTypes\Type;

class UncaughtCalculator implements ExceptionSetCalculatorInterface {

	/** @var Type[][]|\SplObjectStorage */
	private $scopes;
	/** @var Type[][]|\SplObjectStorage */
	private $guarded_scopes;

	/** @var \SplObjectStorage */
	private $catch_clause_catches;

	/** @var CaughtExceptionTypesCalculator $catch_clause_type_resolver */
	private $catch_clause_type_resolver;

	/** @var EncountersCalculator */
	private $encounters_calculator;

	/** @var State $state */
	private $state;

	public function __construct(State $state, CaughtExceptionTypesCalculator $catch_clause_type_resolver, EncountersCalculator $encounters_calculator) {
		$this->scopes = new \SplObjectStorage;
		$this->guarded_scopes = new \SplObjectStorage;
		$this->catch_clause_catches = new \SplObjectStorage;
		$this->state = $state;
		$this->catch_clause_type_resolver = $catch_clause_type_resolver;
		$this->encounters_calculator = $encounters_calculator;
	}

	public function leaveScope(Scope $scope) {
		$uncaught = [];
		foreach ($scope->getGuardedScopes() as $guarded_scope) {
			$uncaught = array_merge($uncaught, $this->getForGuardedScope($guarded_scope));
		}
		$this->scopes[$scope] = $uncaught;
	}

	public function leaveGuardedScope(GuardedScope $guarded_scope) {
		$inclosed_encounters = $this->encounters_calculator->getForScope($guarded_scope->getInclosedScope());

		foreach ($guarded_scope->getCatchClauses() as $catch_clause) {
			$potentially_caught_types = $this->catch_clause_type_resolver->getCaughtTypesForClause($catch_clause);

			$actually_caught_types = array_intersect($inclosed_encounters, $potentially_caught_types);
			$this->catch_clause_catches[$catch_clause] = $actually_caught_types;
			$inclosed_encounters = array_diff($inclosed_encounters, $actually_caught_types);
		}
		//after looping over all catch clauses, $inclosed_encounters contains all exceptions that have not been caught.
		$this->guarded_scopes[$guarded_scope] = $inclosed_encounters;
	}




	private function getForGuardedScope(GuardedScope $guarded_scope) {
		if ($this->guarded_scopes->contains($guarded_scope) === false) {
			throw new \UnexpectedValueException(sprintf("Guarded Scope enclosed by Scope %s could not be found in this set.", $guarded_scope->getEnclosingScope()->getName()));
		}
		return $this->guarded_scopes[$guarded_scope];
	}

	/**
	 * @param Scope $scope
	 * @throws \UnexpectedValueException
	 * @return Type[]
	 */
	public function getForScope(Scope $scope) {
		if ($this->scopes->contains($scope) === false) {
			throw new \UnexpectedValueException(sprintf("Scope with name %s could not be found in this set.", $scope->getName()));
		}
		return $this->scopes[$scope];
	}

	public function getType() {
		return "uncaught";
	}
}