<?php
namespace PhpExceptionFlow\CallGraphConstruction;

use PhpExceptionFlow\Collection\PartialOrder\PartialOrder;

class AppliesToCalculatorTest extends \PHPUnit_Framework_TestCase {

	/** @var array $resolves */
	private $resolves;
	/** @var array $resolved_by */
	private $resolved_by;
	/** @var PartialOrder $partial_order */
	private $partial_order;
	/** @var AppliesToCalculator $applies_to_calculator */
	private $applies_to_calculator;

	public function setUp() {
		/*
		 * Resolves and resolved by represent the following class hierarchy:
		 *          a           f
		 *        /  \          |
		 *        b   c         e
		 *            |
		 *            d
		 */

		$this->resolves = [
			"a" => [
				"a" => "a",
			],
			"b" => [
				"a" => "a",
				"b" => "b",
			],
			"c" => [
				"a" => "a",
				"c" => "c",
			],
			"d" => [
				"a" => "a",
				"c" => "c",
				"d" => "d",
			],
			"e" => [
				"e" => "e",
			],
			"f" => [
				"e" => "e",
				"f" => "f",
			],
		];
		$this->resolved_by = [
			"a" => [
				"a" => "a",
				"b" => "b",
				"c" => "c",
				"d" => "d",
			],
			"b" => [
				"b" => "b",
			],
			"c" => [
				"c" => "c",
				"d" => "d",
			],
			"d" => [
				"d" => "d",
			],
			"e" => [
				"e" => "e",
				"f" => "f",
			],
			"f" => [
				"f" => "f",
			],
		];
		$this->partial_order = new PartialOrder(new MethodComparator($this->resolves));
		$this->applies_to_calculator = new AppliesToCalculator($this->partial_order, $this->resolved_by);
	}

	public function testWithEmptyOrder() {
		$this->expectException(\UnexpectedValueException::class);
		$this->applies_to_calculator->calculateAppliesTo($this->buildMethodMock("a", "m"));
	}

	public function testWithNoOverridingMethodsIsEqualToCone() {
		$method_a_m = $this->buildMethodMock("a", "m");
		$this->partial_order->addElement($method_a_m);

		$applies_to = $this->applies_to_calculator->calculateAppliesTo($method_a_m);
		$this->assertEquals(array_values($this->resolved_by["a"]), $applies_to->evaluate());
	}

	public function testMethodWithSameNameInDifferentHierarchyStillIsEqualToCone() {
		$method_a_m = $this->buildMethodMock("a", "m");
		$method_e_m = $this->buildMethodMock("e", "m");
		$this->partial_order->addElement($method_a_m);
		$this->partial_order->addElement($method_e_m);

		$applies_to_a_m = $this->applies_to_calculator->calculateAppliesTo($method_a_m);
		$applies_to_e_m = $this->applies_to_calculator->calculateAppliesTo($method_e_m);
		$this->assertEquals(array_values($this->resolved_by["a"]), $applies_to_a_m->evaluate());
		$this->assertEquals(array_values($this->resolved_by["e"]), $applies_to_e_m->evaluate());
	}

	public function testOverriddenMethodCancelsOutPartOfHierarchy() {
		$method_a_m = $this->buildMethodMock("a", "m");
		$method_c_m = $this->buildMethodMock("c", "m");
		$this->partial_order->addElement($method_a_m);
		$this->partial_order->addElement($method_c_m);

		$applies_to_a_m = $this->applies_to_calculator->calculateAppliesTo($method_a_m);
		$applies_to_c_m = $this->applies_to_calculator->calculateAppliesTo($method_c_m);
		$this->assertEquals(array("a", "b"), $applies_to_a_m->evaluate());
		$this->assertEquals(array_values($this->resolved_by["c"]), $applies_to_c_m->evaluate());
	}

	public function testMethodsWithDifferentNamesDontAffectEachOther() {
		$method_a_m = $this->buildMethodMock("a", "m");
		$method_a_f = $this->buildMethodMock("a", "f");
		$method_b_m = $this->buildMethodMock("b", "m");
		$method_c_f = $this->buildMethodMock("c", "f");

		$this->partial_order->addElement($method_a_m);
		$this->partial_order->addElement($method_a_f);
		$this->partial_order->addElement($method_b_m);
		$this->partial_order->addElement($method_c_f);

		$applies_to_a_m = $this->applies_to_calculator->calculateAppliesTo($method_a_m);
		$applies_to_a_f = $this->applies_to_calculator->calculateAppliesTo($method_a_f);
		$applies_to_b_m = $this->applies_to_calculator->calculateAppliesTo($method_b_m);
		$applies_to_c_f = $this->applies_to_calculator->calculateAppliesTo($method_c_f);

		$this->assertEquals(array("a", "c", "d"), $applies_to_a_m->evaluate());
		$this->assertEquals(array("a", "b"), $applies_to_a_f->evaluate());
		$this->assertEquals(array("b"), $applies_to_b_m->evaluate());
		$this->assertEquals(array("c", "d"), $applies_to_c_f->evaluate());

	}

	/**
	 * @param string $class
	 * @param string $method_name
	 * @throws \PHPUnit_Framework_Exception
	 * @return Method
	 */
	private function buildMethodMock($class, $method_name) {
		$method_mock = $this->createMock(Method::class);
		$method_mock->method('getClass')->willReturn($class);
		$method_mock->method('getName')->willReturn($method_name);
		return $method_mock;
	}
}