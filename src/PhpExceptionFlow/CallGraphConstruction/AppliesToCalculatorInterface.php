<?php
namespace PhpExceptionFlow\CallGraphConstruction;

use PhpExceptionFlow\Collection\SetInterface;

interface AppliesToCalculatorInterface {
	/**
	 * @param Method $method
	 * @return SetInterface
	 */
	public function calculateAppliesTo(Method $method);
}