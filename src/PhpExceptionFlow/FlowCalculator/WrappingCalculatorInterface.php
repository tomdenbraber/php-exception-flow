<?php
namespace PhpExceptionFlow\FlowCalculator;

interface WrappingCalculatorInterface extends FlowCalculatorInterface {
	/**
	 * @param FlowCalculatorInterface $exception_set_calculator
	 * @return void
	 * @throws \LogicException if the wrapper cannot wrap another calculator
	 */
	public function addCalculator(FlowCalculatorInterface $exception_set_calculator);

	/**
	 * @return FlowCalculatorInterface[]
	 */
	public function getWrappedCalculators();

}