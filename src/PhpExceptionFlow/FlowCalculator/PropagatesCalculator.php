<?php
namespace PhpExceptionFlow\FlowCalculator;

use PhpExceptionFlow\Scope\Scope;

class PropagatesCalculator extends AbstractMutableFlowCalculator {
	/** @var \SplObjectStorage|Scope[][]  */
	private $scope_calls_scopes;

	/** @var FlowCalculatorInterface $encounters_calculator */
	private $encounters_calculator;

	public function __construct(\SplObjectStorage $scope_calls_scopes, FlowCalculatorInterface $encounters_calculator) {
		parent::__construct();
		$this->scope_calls_scopes = $scope_calls_scopes;
		$this->encounters_calculator = $encounters_calculator;
	}

	public function determineForScope(Scope $scope) {
		$imported_exceptions = [];
		if ($this->scope_calls_scopes->contains($scope) === true) {
			$called_scopes = $this->scope_calls_scopes[$scope];
			$imported_exceptions = [];
			/** @var Scope $called_scope */
			foreach ($called_scopes as $called_scope) {
				$callee_encounters = $this->encounters_calculator->getForScope($called_scope);
				$imported_exceptions = array_merge($imported_exceptions, $callee_encounters);
			}
			$imported_exceptions = array_values(array_unique($imported_exceptions));
		}
		$this->setScopeHasChanged($scope, $imported_exceptions);
		$this->scopes[$scope] = $imported_exceptions;
	}

	public function getType() {
		return "propagates";
	}
}