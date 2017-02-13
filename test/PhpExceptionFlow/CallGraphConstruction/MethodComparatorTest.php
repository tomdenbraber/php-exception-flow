<?php
namespace PhpExceptionFlow\Test\CallGraphConstruction;

use PhpExceptionFlow\CallGraphConstruction\MethodComparator;
use PhpExceptionFlow\CallGraphConstruction\Method;
use PhpParser\Node\Stmt\ClassMethod;
use PhpExceptionFlow\Collection\PartialOrder\PartialOrder;
use PHPTypes\State;


class MethodComparatorTest extends \PHPUnit_Framework_TestCase {
	private $state;

	/** @var MethodComparator */
	private $comparator;

	/** @var ClassMethod */
	private $method_m;

	/** @var ClassMethod */
	private $method_f;

	public function setUp() {
		$this->state = $this->createMock(State::class);
		$this->comparator = new MethodComparator($this->state);
		$this->method_m = new ClassMethod("m");
		$this->method_f = new ClassMethod("f");
	}

	public function testMethodsWithDifferentNamesAreNotComparable() {
		$m1 = new Method("a", $this->method_m);
		$m2 = new Method("a", $this->method_f);
		$this->assertEquals(PartialOrder::NOT_COMPARABLE, $this->comparator->compare($m1, $m2));
	}

	public function testMethodsWithSameNamesAndSameClassAreEqual() {
		$m1 = new Method("a", $this->method_m);
		$m2 = new Method("a", $this->method_m);
		$this->assertEquals(PartialOrder::EQUAL, $this->comparator->compare($m1, $m2));
	}

	public function testOverridingMethodIsSmaller() {
		$this->setStateToClassHierarchyWithOnlyClasses();
		$m1 = new Method("b", $this->method_m);
		$m2 = new Method("a", $this->method_m);
		$this->assertEquals(PartialOrder::SMALLER, $this->comparator->compare($m1, $m2));
	}

	public function testOverriddenMethodIsGreater() {
		$this->setStateToClassHierarchyWithOnlyClasses();
		$m1 = new Method("a", $this->method_m);
		$m2 = new Method("b", $this->method_m);
		$this->assertEquals(PartialOrder::GREATER, $this->comparator->compare($m1, $m2));
	}

	public function testOverriddenDeeperInChainMethodIsGreater() {
		$this->setStateToClassHierarchyWithOnlyClasses();
		$m1 = new Method("a", $this->method_m);
		$m2 = new Method("e", $this->method_m);
		$this->assertEquals(PartialOrder::GREATER, $this->comparator->compare($m1, $m2));
	}

	public function testSiblingsAreNotComparable() {
		$this->setStateToClassHierarchyWithOnlyClasses();
		$m1 = new Method("d", $this->method_m);
		$m2 = new Method("e", $this->method_m);
		$this->assertEquals(PartialOrder::NOT_COMPARABLE, $this->comparator->compare($m1, $m2));
	}

	public function testNonRelatedClassesAreNotComparable() {
		$this->setStateToClassHierarchyWithOnlyClasses();
		$m1 = new Method("a", $this->method_m);
		$m2 = new Method("f", $this->method_m);
		$this->assertEquals(PartialOrder::NOT_COMPARABLE, $this->comparator->compare($m1, $m2));
	}

	public function testDerivingClassMethodSmallerThanBaseClassWithTraitInBetween() {
		$this->setStateToClassHierarchyWithClassesAndTraits();
		$m1 = new Method("b", $this->method_m);
		$m2 = new Method("a", $this->method_m);
		$this->assertEquals(PartialOrder::SMALLER, $this->comparator->compare($m1, $m2));
	}

	public function testTraitMethodGreaterThanUsingClassMethod() {
		$this->setStateToClassHierarchyWithClassesAndTraits();
		$m1 = new Method("t1", $this->method_m);
		$m2 = new Method("b", $this->method_m);
		$this->assertEquals(PartialOrder::GREATER, $this->comparator->compare($m1, $m2));
	}

	public function testClassMethodAndTraitMethodNonRelatedAreNotComparable() {
		$this->setStateToClassHierarchyWithClassesAndTraits();
		$m1 = new Method("t1", $this->method_m);
		$m2 = new Method("c", $this->method_m);
		$this->assertEquals(PartialOrder::NOT_COMPARABLE, $this->comparator->compare($m1, $m2));
	}

	public function testMethodInTraitUsedInDerivedClassIsSmallerThanBaseClass() {
		$this->setStateToClassHierarchyWithClassesAndTraits();
		$m1 = new Method("t1", $this->method_m);
		$m2 = new Method("a", $this->method_m);
		$this->assertEquals(PartialOrder::SMALLER, $this->comparator->compare($m1, $m2));
	}

	/**
	 * builds hierarchy representing the following class hierarchy:
	 *          a           f
	 *        /   \
	 *       b     c
	 *     /  \
	 *   d     e
	 */
	private function setStateToClassHierarchyWithOnlyClasses() {
		$this->state->method("isClass")->willReturn(true);
		$this->state->method("isTrait")->willReturn(false);
		$this->state->method("isInterface")->willReturn(false);

		$this->state->classResolvedBy = [
			"a" => [
				"b" => "b",
				"c" => "c",
				"d" => "d",
				"e" => "e",
			],
			"b" => [
				"b" => "b",
				"d" => "d",
				"e" => "e",
			],
			"c" => [
				"c" =>  "e",
			],
			"d" => [
				"d" => "d",
			],
			"e" => [
				"e" => "e",
			],
			"f" => [
				"f" => "f",
			],
		];


		$this->state->classResolves = [
			"a" => [
				"a" => "a",
			],
			"f" => [
				"f" => "f",
			],
			"b" => [
				"a" => "a",
				"b" => "b",
			],
			"c" => [
				"a" => "a",
				"c" => "c"
			],
			"d" => [
				"a" => "a",
				"b" => "b",
				"d" => "d"
			],
			"e" => [
				"a" => "a",
				"b" => "b",
				"e" => "e"
			],
		];
	}

	/**
	 *         a          traits: t1, t2
	 *    /        \
	 * b[t1]       c[t1]
	 */
	private function setStateToClassHierarchyWithClassesAndTraits() {
		$is_class_map = [
			["a", true],
			["b", true],
			["c", true],
			["t1", false],
			["t2", false],
		];

		$is_trait_map = [
			["a", false],
			["b", false],
			["c", false],
			["t1", true],
			["t2", true],
		];

		$this->state->method("isClass")->will($this->returnValueMap($is_class_map));
		$this->state->method("isTrait")->will($this->returnValueMap($is_trait_map));

		$this->state->classResolvedBy = [
			"a" => [
				"a" => "a",
				"b" => "b"
			],
			"b" => [
				"b" => "b"
			],
			"c" => [
				"c" => "c",
			],
			"t1" => [
				"t1" => "t1",
				"b" => "b",
			],
			"t2" => [
				"t2" => "t2",
				"c" => "c",
			],
		];

		$this->state->classResolves = [
			"a" => [
				"a" => "a",
			],
			"t1" => [
				"t1" => "t1"
			],
			"t2" => [
				"t2" => "t2"
			],
			"b" => [
				"b" => "b",
				"a" => "a",
				"t1" => "t1",
			],
			"c" => [
				"c" => "c",
				"a" => "a",
				"t2" => "t2",
			]
		];
	}
}