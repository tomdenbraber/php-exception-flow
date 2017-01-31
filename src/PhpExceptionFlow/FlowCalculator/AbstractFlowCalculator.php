<?php
namespace PhpExceptionFlow\FlowCalculator;

use PhpExceptionFlow\Scope;
use PHPTypes\Type;

abstract class AbstractFlowCalculator implements FlowCalculatorInterface {
	/** @var \SplObjectStorage $has_changed */
	private $has_changed;
	/** @var \SplObjectStorage|Type[][] $scopes */
	protected $scopes;

	public function __construct() {
		$this->has_changed = new \SplObjectStorage;
		$this->scopes = new \SplObjectStorage;
	}

	/**
	 * @param Scope $scope
	 * @param bool $reset
	 * @return bool
	 */
	public function scopeHasChanged(Scope $scope, $reset = true) {
		if ($this->has_changed->contains($scope) === true) {
			$changed = $this->has_changed[$scope];
		} else {
			//we have no information whether the scope changed, so it is probably not yet analyzed.
			//conservative assumption: it changes.
			$changed = true;
		}
		if ($reset === true) {
			$this->has_changed[$scope] = false;
		}
		return $changed;
	}

	/**
	 * @param Scope $scope
	 * @param array $new_exception_set
	 * @return void
	 */
	protected function setScopeHasChanged(Scope $scope, array $new_exception_set) {
		if ($this->scopes->contains($scope) === true) {
			$diff = array_diff($new_exception_set, $this->scopes[$scope]);
		} else {
			$diff = $new_exception_set;
		}
		$this->has_changed[$scope] = !empty($diff);
	}
}