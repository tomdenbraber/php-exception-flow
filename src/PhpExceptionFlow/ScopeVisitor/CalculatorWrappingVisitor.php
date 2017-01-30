<?php
namespace PhpExceptionFlow\ScopeVisitor;

use PhpExceptionFlow\FlowCalculator\ExceptionSetCalculatorInterface;
use PhpExceptionFlow\Scope;

class CalculatorWrappingVisitor extends AbstractScopeVisitor {
	/** @var ExceptionSetCalculatorInterface */
	private $flow_calculator;

	private $operating_mode;

	const CALCULATE_ON_ENTER = 1;
	const CALCULATE_ON_LEAVE = 2;
	const CALCULATE_ON_ENTER_AND_LEAVE = 3;

	/**
	 * CalculatorWrappingVisitor constructor.
	 * @param ExceptionSetCalculatorInterface $calculator
	 * @param int $operating_mode, either one of CALCULATE_ON_ENTER, CALCULATE_ON_LEAVE, CALCULATE_ON_ENTER_AND_LEAVE
	 */
	public function __construct(ExceptionSetCalculatorInterface $calculator, $operating_mode) {
		$this->flow_calculator = $calculator;
		$this->operating_mode = $operating_mode;
	}

	/**
	 * @return ExceptionSetCalculatorInterface
	 */
	public function getCalculator() {
		return $this->flow_calculator;
	}

	public function enterScope(Scope $scope) {
		if ($this->operating_mode === self::CALCULATE_ON_ENTER || $this->operating_mode === self::CALCULATE_ON_ENTER_AND_LEAVE) {
			$this->flow_calculator->determineForScope($scope);
		}
	}

	public function leaveScope(Scope $scope) {
		if ($this->operating_mode === self::CALCULATE_ON_LEAVE || $this->operating_mode === self::CALCULATE_ON_ENTER_AND_LEAVE) {
			$this->flow_calculator->determineForScope($scope);
		}
	}

}