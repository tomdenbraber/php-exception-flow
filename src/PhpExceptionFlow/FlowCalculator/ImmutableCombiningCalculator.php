<?php
namespace PhpExceptionFlow\FlowCalculator;

class ImmutableCombiningCalculator extends AbstractCombiningCalculator {

	/**
	 * @param FlowCalculatorInterface $exception_set_calculator
	 * @throws \LogicException when two of the same type exceptionset calculators are given, or when it is a mutable one
	 */
	public function addCalculator(FlowCalculatorInterface $exception_set_calculator) {
		if ($exception_set_calculator instanceof MutableFlowCalculatorInterface === true) {
			throw new \LogicException("Cannot add a mutable flow calculator to an immutable combinating calculator");
		}
		parent::addCalculator($exception_set_calculator);
	}

	public function getType() {
		return "immutable combined";
	}
}