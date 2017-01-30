<?php
namespace PhpExceptionFlow\FlowCalculator;

use PhpExceptionFlow\Scope;

class CombiningCalculator implements ExceptionSetCalculatorInterface {

	/** @var ExceptionSetCalculatorInterface[] */
	private $calculators = [];

	/**
	 * @param ExceptionSetCalculatorInterface $exception_set_calculator
	 * @throws \LogicException when two of the same type exceptionset calculators are given
	 */
	public function addCalculator(ExceptionSetCalculatorInterface $exception_set_calculator) {
		if (isset($this->calculators[$exception_set_calculator->getType()]) === true) {
			throw new \LogicException(sprintf("Cannot add the same calculator (type %s) to the encounterscalculator.", $exception_set_calculator->getType()));
		}
		$this->calculators[$exception_set_calculator->getType()] = $exception_set_calculator;
	}

	/**
	 * @param string $type
	 * @return ExceptionSetCalculatorInterface
	 * @throws \UnexpectedValueException
	 */
	public function getCalculator($type) {
		if (isset($this->calculators[$type])) {
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


	public function getForScope(Scope $scope) {
		$exception_set = [];
		foreach ($this->calculators as $calculator) {
			$exception_set = array_merge($calculator->getForScope($scope), $exception_set);
		}
		return array_values(array_unique($exception_set));
	}

	 public function getType() {
		return "combined";
	 }
}