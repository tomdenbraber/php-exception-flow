<?php
namespace PhpExceptionFlow\ScopeVisitor;

use PhpExceptionFlow\FlowCalculator\FlowCalculatorInterface;
use PhpExceptionFlow\Scope;

class CalculatorWrappingVisitorTest extends \PHPUnit_Framework_TestCase {
	/** @var $calculator_mock \PHPUnit_Framework_MockObject_MockObject */
	private $calculator_mock;

	public function setUp() {
		$this->calculator_mock = $this->createMock(FlowCalculatorInterface::class);
		$this->calculator_mock->method("getType")->willReturn("But in the end, it doesn't even matter");
	}

	public function testWithModeOnEnter() {
		$visitor = new CalculatorWrappingVisitor($this->calculator_mock, CalculatorWrappingVisitor::CALCULATE_ON_ENTER);
		$scope_mock = $this->createMock(Scope::class);

		$this->calculator_mock->expects($this->once())
			->method("determineForScope")
			->with($scope_mock);
		$visitor->enterScope($scope_mock);
		$visitor->leaveScope($scope_mock);
	}

	public function testWithModeOnLeave() {
		$visitor = new CalculatorWrappingVisitor($this->calculator_mock, CalculatorWrappingVisitor::CALCULATE_ON_LEAVE);
		$scope_mock = $this->createMock(Scope::class);

		$this->calculator_mock->expects($this->exactly(1))
			->method("determineForScope")
			->with($scope_mock);
		$visitor->enterScope($scope_mock);
		$visitor->leaveScope($scope_mock);
	}

	public function testWithModeOnEnterAndOnLeave() {
		$visitor = new CalculatorWrappingVisitor($this->calculator_mock, CalculatorWrappingVisitor::CALCULATE_ON_ENTER_AND_LEAVE);
		$scope_mock = $this->createMock(Scope::class);

		$this->calculator_mock->expects($this->exactly(2))
			->method("determineForScope")
			->with($scope_mock);
		$visitor->enterScope($scope_mock);
		$visitor->leaveScope($scope_mock);
	}
}