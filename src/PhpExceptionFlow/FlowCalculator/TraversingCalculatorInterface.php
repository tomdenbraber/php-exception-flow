<?php
namespace PhpExceptionFlow\FlowCalculator;

use PhpExceptionFlow\Scope;

interface TraversingCalculatorInterface extends WrappingCalculatorInterface {
	/**
	 * @return Scope[]
	 */
	public function getScopesChangedDuringLastTraverse();
}