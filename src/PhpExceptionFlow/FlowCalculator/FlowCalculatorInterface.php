<?php
namespace PhpExceptionFlow\FlowCalculator;

use PhpExceptionFlow\Scope;
use PHPTypes\Type;

interface FlowCalculatorInterface {
	/**
	 * Returns the exception type that this calculator will return
	 * @return string
	 */
	public function getType();

	/**
	 * @param Scope $scope
	 * @return void;
	 */
	public function determineForScope(Scope $scope);

	/**
	 * @param Scope $scope
	 * @return Type[]
	 * @throws \UnexpectedValueException when a scope is given for which this calculator has no data
	 */
	public function getForScope(Scope $scope);


	/**
	 * Returns whether the last determineForScope call changed the exceptions encountered for the given scope
	 * @param Scope $scope
	 * @param bool $reset = true; if reset is true, the next call scopeHasChanged w/o first calling determineForScope will return false.
	 * @return boolean
	 */
	public function scopeHasChanged(Scope $scope, $reset = true);
}