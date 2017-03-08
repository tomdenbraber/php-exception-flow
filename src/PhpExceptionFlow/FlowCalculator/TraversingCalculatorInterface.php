<?php
namespace PhpExceptionFlow\FlowCalculator;

use PhpExceptionFlow\Scope\Scope;

interface TraversingCalculatorInterface extends WrappingCalculatorInterface {
	/**
	 * @return Scope[]
	 */
	public function getScopesChangedDuringLastTraverse();
}