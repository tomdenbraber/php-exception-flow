<?php
namespace PhpExceptionFlow\FlowCalculator;

use PhpExceptionFlow\Scope;

class MutableCombiningCalculator implements MutableFlowCalculatorInterface {

	/** @var MutableFlowCalculatorInterface[] */
	private $calculators = [];

	/**
	 * @param MutableFlowCalculatorInterface $exception_set_calculator
	 * @throws \LogicException when two of the same type exceptionset calculators are given
	 */
	public function addCalculator(MutableFlowCalculatorInterface $exception_set_calculator) {
		if (isset($this->calculators[$exception_set_calculator->getType()]) === true) {
			throw new \LogicException(sprintf("Cannot add the same calculator (type %s) to the encounterscalculator.", $exception_set_calculator->getType()));
		}
		$this->calculators[$exception_set_calculator->getType()] = $exception_set_calculator;
	}

	/**
	 * @param string $type
	 * @return FlowCalculatorInterface
	 * @throws \UnexpectedValueException
	 */
	public function getCalculator($type) {
		if (isset($this->calculators[$type]) === true) {
			return $this->calculators[$type];
		} else {
			throw new \UnexpectedValueException(sprintf("No calculator registered for type %s", $type));
		}
	}

	/**
	 * @param Scope $scope
	 */
	public function determineForScope(Scope $scope) {
		foreach ($this->calculators as $calculator) {
			$calculator->determineForScope($scope);
		}
	}

	/**
	 * @param Scope $scope
	 * @return array
	 * @throws \UnexpectedValueException
	 */
	public function getForScope(Scope $scope) {
		$exception_set = [];
		foreach ($this->calculators as $calculator) {
			$exception_set = array_merge($calculator->getForScope($scope), $exception_set);
		}
		return array_values(array_unique($exception_set));
	}

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

	public function getType() {
		return "combined";
	}
}