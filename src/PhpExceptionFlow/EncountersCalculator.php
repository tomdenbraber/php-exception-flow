<?php
namespace PhpExceptionFlow;

use PhpExceptionFlow\FlowCalculator\FlowCalculatorInterface;
use PhpExceptionFlow\FlowCalculator\TraversingCalculatorInterface;
use PhpExceptionFlow\FlowCalculator\WrappingCalculatorInterface;
use PhpExceptionFlow\Scope\Scope;

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

	/** @var Scope[][]|\SplObjectStorage */
	private $callee_called_by_scopes;

	public function __construct(FlowCalculatorInterface $mutable_flow_calc, FlowCalculatorInterface $immutable_flow_calculator, \SplObjectStorage $callee_called_by_scopes) {
		$this->mutable_flow_calculator = $mutable_flow_calc;
		$this->immutable_flow_calculator = $immutable_flow_calculator;
		$this->callee_called_by_scopes = $callee_called_by_scopes;

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
			print sprintf("Fetched scope with name %s; still %d scopes on worklist\n", $scope->getName(), count($this->worklist));
			$this->mutable_flow_calculator->determineForScope($scope);
			foreach ($this->getChangedScopesDueToTraverse($this->mutable_flow_calculator) as $scope) {
				$this->addAffectedScopesToWorklist($scope);
			}
		}
	}

	/**
	 * @param Scope $scope
	 */
	private function addAffectedScopesToWorklist(Scope $scope) {
		while ($scope->isEnclosed() === true) {
			$scope = $scope->getEnclosingGuardedScope()->getEnclosingScope();
			$this->addToWorklist($scope);
		}

		if ($this->callee_called_by_scopes->contains($scope) === true) {
			$calling_scopes = $this->callee_called_by_scopes[$scope];
			/**
			 * @var Scope[] $calling_scopes
			 */
			foreach ($calling_scopes as $calling_scope) {
				$this->addToWorklist($calling_scope);
			}
		}
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

	/**
	 * Will recursively find all TraverserCalculatorInterface wrapped by the given $calculator, and return the result
	 * of merging their changed scopes
	 * @param FlowCalculatorInterface $calculator
	 * @return Scope[]
	 */
	private function getChangedScopesDueToTraverse(FlowCalculatorInterface $calculator) {
		$changed = [];
		if ($calculator instanceof TraversingCalculatorInterface) {
			$changed = $calculator->getScopesChangedDuringLastTraverse();
		} else if ($calculator instanceof WrappingCalculatorInterface) {
			foreach ($calculator->getWrappedCalculators() as $wrapped_calculator) {
				$changed = array_merge($changed, $this->getChangedScopesDueToTraverse($wrapped_calculator));
			}
		}
		return $changed;
	}

}