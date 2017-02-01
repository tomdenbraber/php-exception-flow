<?php
namespace PhpExceptionFlow\ScopeVisitor;

use PhpExceptionFlow\FlowCalculator\FlowCalculatorInterface;
use PhpExceptionFlow\Scope;

class CalculatorWrappingVisitor extends AbstractScopeVisitor {
	/** @var FlowCalculatorInterface */
	private $flow_calculator;

	private $operating_mode;

	const CALCULATE_ON_ENTER = 1;
	const CALCULATE_ON_LEAVE = 2;
	const CALCULATE_ON_ENTER_AND_LEAVE = 3;

	/** @var Scope[] */
	private $changed_in_last_traverse = [];

	/**
	 * CalculatorWrappingVisitor constructor.
	 * @param FlowCalculatorInterface $calculator
	 * @param int $operating_mode, either one of CALCULATE_ON_ENTER, CALCULATE_ON_LEAVE, CALCULATE_ON_ENTER_AND_LEAVE
	 */
	public function __construct(FlowCalculatorInterface $calculator, $operating_mode) {
		$this->flow_calculator = $calculator;
		$this->operating_mode = $operating_mode;
	}

	/**
	 * @return FlowCalculatorInterface
	 */
	public function getCalculator() {
		return $this->flow_calculator;
	}

	public function beforeTraverse(array $scopes) {
		$this->changed_in_last_traverse = [];
	}

	public function enterScope(Scope $scope) {
		if ($this->operating_mode === self::CALCULATE_ON_ENTER || $this->operating_mode === self::CALCULATE_ON_ENTER_AND_LEAVE) {
			$this->runCalculator($scope);
		}
	}

	public function leaveScope(Scope $scope) {
		if ($this->operating_mode === self::CALCULATE_ON_LEAVE || $this->operating_mode === self::CALCULATE_ON_ENTER_AND_LEAVE) {
			$this->runCalculator($scope);
		}
	}

	/**
	 * @return Scope[]
	 */
	public function getChangedDuringLastTraverse() {
		return $this->changed_in_last_traverse;
	}

	private function runCalculator(Scope $scope) {
		$this->flow_calculator->determineForScope($scope);
		if ($this->flow_calculator->scopeHasChanged($scope) === true) {
			$this->changed_in_last_traverse[] = $scope;
		}
	}

}