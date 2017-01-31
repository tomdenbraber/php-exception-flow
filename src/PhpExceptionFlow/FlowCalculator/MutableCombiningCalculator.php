<?php
namespace PhpExceptionFlow\FlowCalculator;

use PhpExceptionFlow\Scope;

class MutableCombiningCalculator extends AbstractCombiningCalculator implements MutableFlowCalculatorInterface {
	/**
	 * @param Scope $scope
	 * @param bool $reset
	 * @return bool
	 */
	public function scopeHasChanged(Scope $scope, $reset = true) {
		$changed = false;
		foreach ($this->calculators as $calculator) {
			$changed = $changed || $calculator->scopeHasChanged($scope, $reset);
		}
		return $changed;
	}

	public function addCalculator(FlowCalculatorInterface $exception_set_calculator) {
		if ($exception_set_calculator instanceof MutableFlowCalculatorInterface === false) {
			throw new \LogicException("Cannot add an immutable flow calculator to n mutable combinating calculator");
		}
		parent::addCalculator($exception_set_calculator);
	}

	public function getType() {
		return "mutual combined";
	}
}