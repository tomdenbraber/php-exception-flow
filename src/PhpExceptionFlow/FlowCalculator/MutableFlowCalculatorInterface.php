<?php
namespace PhpExceptionFlow\FlowCalculator;

use PhpExceptionFlow\Scope;

interface MutableFlowCalculatorInterface extends FlowCalculatorInterface {

	/**
	 * Returns whether the last determineForScope call changed the exceptions encountered for the given scope
	 * @param Scope $scope
	 * @param bool $reset = true; if reset is true, the next call scopeHasChanged w/o first calling determineForScope will return false.
	 * @return boolean
	 */
	public function scopeHasChanged(Scope $scope, $reset = true);
}