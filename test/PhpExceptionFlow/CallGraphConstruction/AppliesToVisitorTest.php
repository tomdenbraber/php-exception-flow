<?php
namespace PhpExceptionFlow\CallGraphConstruction;

use PhpExceptionFlow\Collection\Set\Set;

class AppliesToVisitorTest extends \PHPUnit_Framework_TestCase {

	/** @var  AppliesToVisitor */
	private $applies_to_visitor;

	/** @var AppliesToCalculatorInterface */
	private $applies_to_calculator_mock;

	public function setUp() {
		$this->applies_to_calculator_mock = $this->createMock(AppliesToCalculatorInterface::class);
		$this->applies_to_visitor = new AppliesToVisitor($this->applies_to_calculator_mock);
	}

	public function testCorrectMethodCallsOnVisit() {
		$method_mock = $this->createMock(Method::class);

		$this->applies_to_calculator_mock->expects($this->once())
			->method("calculateAppliesTo")
			->with($this->equalTo($method_mock))
			->willReturn(new Set(array("a")));

		$method_mock->expects($this->once())
			->method("getName")
			->willReturn("m");

		$this->applies_to_visitor->visitElement($method_mock);
	}

	public function testCorrectAppliesToOutput() {
		/*
		 * Partial order that is used:
		 *      a.m
		 *    /
		 *   b.m
		 * Hierarchy is:
		 *      a
		 *    /  \
		 *  b     c
		 *        |
		 *        d
		 */
		$method_mock_a_m = $this->createMock(Method::class);
		$method_mock_a_m->method("getName")
			->willReturn("m");
		$method_mock_b_m = $this->createMock(Method::class);
		$method_mock_b_m->method("getName")
			->willReturn("m");

		$this->applies_to_calculator_mock->expects($this->exactly(2))
			->method("calculateAppliesTo")
			->withConsecutive(array($this->equalTo($method_mock_a_m)), array($this->equalTo($method_mock_b_m)))
			->will($this->onConsecutiveCalls(new Set(array("a", "c", "d")), new Set(array("b"))));

		$this->applies_to_visitor->visitElement($method_mock_a_m);
		$this->applies_to_visitor->visitElement($method_mock_b_m);
		$class_to_method_map = $this->applies_to_visitor->getClassToMethodMap();

		$this->assertArrayHasKey("a", $class_to_method_map);
		$this->assertArrayHasKey("b", $class_to_method_map);
		$this->assertArrayHasKey("c", $class_to_method_map);
		$this->assertArrayHasKey("d", $class_to_method_map);
		$this->assertArrayHasKey("m", $class_to_method_map["a"]);
		$this->assertArrayHasKey("m", $class_to_method_map["b"]);
		$this->assertArrayHasKey("m", $class_to_method_map["c"]);
		$this->assertArrayHasKey("m", $class_to_method_map["d"]);
		$this->assertEquals([$method_mock_a_m], $class_to_method_map["a"]["m"]);
		$this->assertEquals([$method_mock_a_m], $class_to_method_map["c"]["m"]);
		$this->assertEquals([$method_mock_a_m], $class_to_method_map["d"]["m"]);
		$this->assertEquals([$method_mock_b_m], $class_to_method_map["b"]["m"]);
	}
}