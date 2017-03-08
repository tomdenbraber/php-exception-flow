<?php
namespace PhpExceptionFlow\FlowCalculator;

use PhpExceptionFlow\Exception_;
use PhpExceptionFlow\Scope\Scope;

class PropagatesCalculatorTest extends \PHPUnit_Framework_TestCase {

	private $combining_calculator;

	public function setUp() {
		$this->combining_calculator = $this->createMock(CombiningCalculator::class);
	}

	public function testGetType() {
		$scope_calls_scopes = new \SplObjectStorage;
		$this->combining_calculator->expects($this->never())
			->method("getForScope");
		$propagates = new PropagatesCalculator($scope_calls_scopes, $this->combining_calculator);
		$this->assertEquals("propagates", $propagates->getType());
	}

	public function testWithNoCallsReturnsEmpty() {
		$scope_calls_scopes = new \SplObjectStorage;
		$this->combining_calculator->expects($this->never())
			->method("getForScope");
		$scope_mock = $this->createScopeMock("a");

		$propagates = new PropagatesCalculator($scope_calls_scopes, $this->combining_calculator);
		$propagates->determineForScope($scope_mock);

		$this->assertEmpty($propagates->getForScope($scope_mock));
	}

	public function testWithCallsThatDoNotEncounterReturnsEmpty() {
		$caller = $this->createScopeMock("a");
		$callee1 = $this->createScopeMock("b");
		$callee2 = $this->createScopeMock("c");

		$scope_calls_scopes = new \SplObjectStorage;
		$scope_calls_scopes->attach($caller, array($callee1, $callee2));

		$this->combining_calculator->expects($this->exactly(2))
			->method("getForScope")
			->withConsecutive(array($callee1), array($callee2))
			->willReturn(array());

		$propagates = new PropagatesCalculator($scope_calls_scopes, $this->combining_calculator);
		$propagates->determineForScope($caller);

		$this->assertEmpty($propagates->getForScope($caller));
	}

	public function testWithCallsThatDoEncounterReturnsCorrectExceptions() {
		$caller = $this->createScopeMock("a");
		$callee1 = $this->createScopeMock("b");
		$callee2 = $this->createScopeMock("c");

		$exception_x_1 = $this->createMock(Exception_::class);
		$exception_x_1->method("getType")
			->willReturn("x");
		$exception_x_2 = $this->createMock(Exception_::class);
		$exception_x_2->method("getType")
			->willReturn("x");
		$exception_y = $this->createMock(Exception_::class);
		$exception_y->method("getType")
			->willReturn("y");

		//x_1 will be propagated from callee1 to caller
		$exception_x_1->expects($this->exactly(1))
			->method("propagate")
			->with($callee1, $caller);

		//x_2, y will be propagated from callee2 to caller
		$exception_x_2->expects($this->exactly(1))
			->method("propagate")
			->with($callee2, $caller);
		$exception_y->expects($this->once())
			->method("propagate")
			->with($callee2, $caller);

		$scope_calls_scopes = new \SplObjectStorage;
		$scope_calls_scopes->attach($caller, array($callee1, $callee2));

		$this->combining_calculator->expects($this->exactly(2))
			->method("getForScope")
			->withConsecutive(array($callee1), array($callee2))
			->will($this->onConsecutiveCalls(array($exception_x_1), array($exception_x_2, $exception_y)));

		$propagates = new PropagatesCalculator($scope_calls_scopes, $this->combining_calculator);

		$propagates->determineForScope($caller);
		$this->assertEquals(array($exception_x_1, $exception_x_2, $exception_y), $propagates->getForScope($caller));
	}

	private function createScopeMock($name) {
		$scope_mock = $this->createMock(Scope::class);
		$scope_mock->method("getName")
			->willReturn($name);
		return $scope_mock;
	}
}