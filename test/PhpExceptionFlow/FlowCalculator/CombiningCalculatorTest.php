<?php
namespace PhpExceptionFlow\FlowCalculator;

use PhpExceptionFlow\Scope\Scope;

class CombiningCalculatorTest extends \PHPUnit_Framework_TestCase {
	/** @var CombiningCalculator $combining_calculator */
	private $combining_calculator;

	public function setUp() {
		$this->combining_calculator = new CombiningCalculator();
	}

	public function testGetType() {
		$this->assertEquals("combined", $this->combining_calculator->getType());
	}

	public function testWithoutCalculatorsReturnsNoTypes() {
		$scope_mock = $this->createMock(Scope::class);
		$this->assertEmpty($this->combining_calculator->getForScope($scope_mock));
	}

	public function testAddTwoSameTypeCalculatorsRaisesException() {
		$calc_mock_1 = $this->createMock(FlowCalculatorInterface::class);
		$calc_mock_2 = $this->createMock(FlowCalculatorInterface::class);
		$calc_mock_1->method("getType")
			->willReturn("kaas");
		$calc_mock_2->method("getType")
			->willReturn("kaas");

		$this->combining_calculator->addCalculator($calc_mock_1);

		$this->expectException(\LogicException::class);
		$this->combining_calculator->addCalculator($calc_mock_2);
	}

	public function testReturnsOutputOfCalculatorWithOneCalculator() {
		$scope_mock = $this->createMock(Scope::class);

		$calc_mock_1 = $this->createMock(FlowCalculatorInterface::class);
		$calc_mock_1->method("getType")
			->willReturn("kaas");

		$calc_mock_1->expects($this->once())
			->method("determineForScope")
			->with($scope_mock);

		$calc_mock_1->expects($this->once())
			->method("getForScope")
			->with($scope_mock)
			->willReturn(array("a", "b"));

		$this->combining_calculator->addCalculator($calc_mock_1);
		$this->combining_calculator->determineForScope($scope_mock);
		$this->assertEquals(array("a", "b"), $this->combining_calculator->getForScope($scope_mock));
	}

	public function testReturnsCombinedOutputOfCalculatorsWithMultipleCalculator() {
		$scope_mock = $this->createMock(Scope::class);

		$calc_mock_1 = $this->createMock(FlowCalculatorInterface::class);
		$calc_mock_1->method("getType")
			->willReturn("cheese");

		$calc_mock_2 = $this->createMock(FlowCalculatorInterface::class);
		$calc_mock_2->method("getType")
			->willReturn("fromage");


		$calc_mock_1->expects($this->once())
			->method("determineForScope")
			->with($scope_mock);
		$calc_mock_2->expects($this->once())
			->method("determineForScope")
			->with($scope_mock);

		$calc_mock_1->expects($this->once())
			->method("getForScope")
			->with($scope_mock)
			->willReturn(array("a", "b"));
		$calc_mock_2->expects($this->once())
			->method("getForScope")
			->with($scope_mock)
			->willReturn(array("b", "c"));

		$this->combining_calculator->addCalculator($calc_mock_1);
		$this->combining_calculator->addCalculator($calc_mock_2);
		$this->combining_calculator->determineForScope($scope_mock);

		$types = $this->combining_calculator->getForScope($scope_mock);
		sort($types);
		$this->assertEquals(array("a", "b", "c"), $types);
	}

	public function testScopeHasChangedWhenWrappedCalculatorsSaySo() {
		$scope_mock = $this->createMock(Scope::class);

		$calc_mock_1 = $this->createMock(FlowCalculatorInterface::class);
		$calc_mock_1->method("getType")
			->willReturn("cheese");
		$calc_mock_1->expects($this->once())
			->method("scopeHasChanged")
			->with($scope_mock, false)
			->willReturn(false);

		$calc_mock_2 = $this->createMock(FlowCalculatorInterface::class);
		$calc_mock_2->method("getType")
			->willReturn("fromage");
		$calc_mock_2->expects($this->once())
			->method("scopeHasChanged")
			->with($scope_mock, false)
			->willReturn(true);

		$this->combining_calculator->addCalculator($calc_mock_1);
		$this->combining_calculator->addCalculator($calc_mock_2);
		$this->assertTrue($this->combining_calculator->scopeHasChanged($scope_mock, false));
	}

	public function testScopeHasNotChangedWhenWrappedCalculatorsReturnFalse() {
		$scope_mock = $this->createMock(Scope::class);

		$calc_mock_1 = $this->createMock(FlowCalculatorInterface::class);
		$calc_mock_1->method("getType")
			->willReturn("cheese");
		$calc_mock_1->expects($this->once())
			->method("scopeHasChanged")
			->with($scope_mock, false)
			->willReturn(false);

		$calc_mock_2 = $this->createMock(FlowCalculatorInterface::class);
		$calc_mock_2->method("getType")
			->willReturn("fromage");
		$calc_mock_2->expects($this->once())
			->method("scopeHasChanged")
			->with($scope_mock, false)
			->willReturn(false);

		$this->combining_calculator->addCalculator($calc_mock_1);
		$this->combining_calculator->addCalculator($calc_mock_2);
		$this->assertFalse($this->combining_calculator->scopeHasChanged($scope_mock, false));
	}
}