<?php
namespace PhpExceptionFlow\FlowCalculator;

use PhpExceptionFlow\Scope\Scope;
use PHPTypes\Type;

abstract class AbstractFlowCalculator implements FlowCalculatorInterface {
	/** @var \SplObjectStorage|Type[][] $scopes */
	protected $scopes;

	public function __construct() {
		$this->scopes = new \SplObjectStorage;
	}

	/**
	 * @param Scope $scope
	 * @throws \UnexpectedValueException
	 * @return Type[]
	 */
	public function getForScope(Scope $scope) {
		if ($this->scopes->contains($scope) === false) {
			throw new \UnexpectedValueException(sprintf("Scope with name %s could not be found in this set.", $scope->getName()));
		}
		return $this->scopes[$scope];
	}

	public function scopeHasChanged(Scope $scope, $reset = true) {
		return false; // not all calculators have to detect change, so return false by default
	}
}