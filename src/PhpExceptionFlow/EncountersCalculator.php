<?php
namespace PhpExceptionFlow;

use PhpExceptionFlow\FlowCalculator\FlowCalculatorInterface;

//todo improve naming.
class EncountersCalculator {

	/** @var FlowCalculatorInterface */
	private $mutable_flow_calculator;
	/** @var FlowCalculatorInterface */
	private $immutable_flow_calculator;

	/** @var Scope[] */
	private $worklist = [];
	/** @var Scope[]|\SplObjectStorage */
	private $queued_scopes;

	/** ScopeTraverserInterface $immutable_calculating_traverser */
	private $immutable_calculating_traverser;

	public function __construct(FlowCalculatorInterface $mutable_flow_calc, FlowCalculatorInterface $immutable_flow_calculator) {
		$this->mutable_flow_calculator = $mutable_flow_calc;
		$this->immutable_flow_calculator = $immutable_flow_calculator;

		$this->queued_scopes = new \SplObjectStorage;
	}


	/**
	 * @param Scope[] $scopes
	 */
	public function calculateEncounters(array $scopes) {
		foreach ($scopes as $scope) {
			$this->immutable_flow_calculator->determineForScope($scope);
			$this->addToWorklist($scope);
		}

		while (false !== ($scope = $this->fetchFromWorklist())) {
			$this->mutable_flow_calculator->determineForScope($scope);
			if ($this->mutable_flow_calculator->scopeHasChanged($scope) === true) {
				$this->addAffectedScopesToWorklist($scope);
			}
		}
	}

	/**
	 * @param Scope $scope
	 */
	private function addAffectedScopesToWorklist(Scope $scope) {
		while ($scope->isEnclosed() === true) {
			$this->addToWorklist($scope);
			$scope = $scope->getEnclosingGuardedScope()->getEnclosingScope();
		}

		//todo add all scopes that call $scope.
	}

	private function addToWorklist(Scope $scope) {
		if ($this->queued_scopes->contains($scope) === false) {
			$this->worklist[] = $scope;
			$this->queued_scopes->attach($scope);
		}
	}

	/**
	 * @return bool|Scope, returns false when worklist is empty
	 */
	private function fetchFromWorklist() {
		if (count($this->worklist) > 0) {
			$scope = array_shift($this->worklist);
			$this->queued_scopes->detach($scope);
			return $scope;
		} else {
			return false;
		}
	}

}