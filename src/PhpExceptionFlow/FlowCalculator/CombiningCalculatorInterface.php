<?php
namespace PhpExceptionFlow\FlowCalculator;

interface CombiningCalculatorInterface extends FlowCalculatorInterface {
	/**
	 * @param string $type
	 * @return FlowCalculatorInterface
	 * @throws \UnexpectedValueException
	 */
	public function getCalculator($type);

	public function addCalculator(FlowCalculatorInterface $exception_set_calculator);

}