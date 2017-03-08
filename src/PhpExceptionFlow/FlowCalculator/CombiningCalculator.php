<?php
namespace PhpExceptionFlow\FlowCalculator;

use PhpExceptionFlow\Exception_;
use PhpExceptionFlow\Scope\Scope;

class CombiningCalculator implements CombiningCalculatorInterface {
	/** @var FlowCalculatorInterface[] $calculators */
	protected $calculators = [];

	/**
	 * @param string $type
	 * @return FlowCalculatorInterface
	 * @throws \UnexpectedValueException
	 */
	public function getCalculator($type) {
		if (isset($this->calculators[$type]) === true) {
			return $this->calculators[$type];
		} else {
			foreach ($this->calculators as $calculator) {
				if ($calculator instanceof CombiningCalculatorInterface) {
					try {
						$correct_type_calc = $calculator->getCalculator($type);
						return $correct_type_calc;
					} catch (\UnexpectedValueException $e) {
						//silently failing is not a problem, maybe we can find the calculator with given type in a wrapped calculator
					}
				}
			}
		}
		throw new \UnexpectedValueException(sprintf("No calculator registered for type %s", $type));
	}

	/**
	 * @param FlowCalculatorInterface $exception_set_calculator
	 * @throws \LogicException when two of the same type exceptionset calculators are given
	 */
	public function addCalculator(FlowCalculatorInterface $exception_set_calculator) {
		if (isset($this->calculators[$exception_set_calculator->getType()]) === true) {
			throw new \LogicException(sprintf("Cannot add the same calculator (type %s) to the encounterscalculator.", $exception_set_calculator->getType()));
		}
		$this->calculators[$exception_set_calculator->getType()] = $exception_set_calculator;
	}

	/**
	 * @return FlowCalculatorInterface[]
	 */
	public function getWrappedCalculators() {
		return $this->calculators;
	}

	/**
	 * @param Scope $scope
	 */
	public function determineForScope(Scope $scope) {
		foreach ($this->calculators as $calculator) {
			$calculator->determineForScope($scope);
		}
	}

	/**
	 * @param Scope $scope
	 * @return Exception_[]
	 * @throws \UnexpectedValueException
	 */
	public function getForScope(Scope $scope) {
		$exc_storage = new \SplObjectStorage;
		foreach ($this->calculators as $calculator) {
			try {
				$calculators_exc = $calculator->getForScope($scope);
			} catch (\UnexpectedValueException $exception) {
				$calculators_exc = [];
			}

			foreach ($calculators_exc as $exception) {
				if ($exc_storage->contains($exception) === false) {
					$exc_storage->attach($exception);
				}
			}
		}
		$exception_set = [];
		foreach ($exc_storage as $exception) {
			$exception_set[] = $exception;
		}
		return $exception_set;
	}

	/**
	 * @param Scope $scope
	 * @param bool $reset
	 * @return bool
	 */
	public function scopeHasChanged(Scope $scope, $reset = true) {
		$changed = false;
		foreach ($this->calculators as $calculator) {
			$changed = $changed || $calculator->scopeHasChanged($scope, $reset);
		}
		return $changed;
	}

	public function getType() {
		$wrapped_types = [];
		foreach ($this->calculators as $type => $_) {
			$wrapped_types[] = $type;
		}
		return count($wrapped_types) === 0 ? "combined" : "combined " . implode(",", $wrapped_types);
	}

}