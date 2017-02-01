<?php
namespace PhpExceptionFlow\FlowCalculator;

interface CombiningCalculatorInterface extends WrappingCalculatorInterface {
	/**
	 * @param string $type
	 * @return FlowCalculatorInterface
	 * @throws \UnexpectedValueException
	 */
	public function getCalculator($type);
}