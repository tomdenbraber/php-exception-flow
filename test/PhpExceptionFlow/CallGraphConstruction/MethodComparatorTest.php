<?php
namespace PhpExceptionFlow\Test\CallGraphConstruction;

use PhpExceptionFlow\CallGraphConstruction\MethodComparator;
use PhpExceptionFlow\CallGraphConstruction\Method;
use PhpParser\Node\Stmt\ClassMethod;
use PhpExceptionFlow\Collection\PartialOrder\PartialOrder;


class MethodComparatorTest extends \PHPUnit_Framework_TestCase {
	/**
	 * @var array
	 * representing the following class hierarchy:
	 *          a           f
	 *        /   \
	 *       b     c
	 *     /  \
	 *   d     e
	 */
	private $resolves = [
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

	/** @var MethodComparator */
	private $comparator;

	/** @var ClassMethod */
	private $method_m;
	/** @var ClassMethod */
	private $method_f;

	public function setUp() {
		$this->comparator = new MethodComparator($this->resolves);
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
		$m1 = new Method("b", $this->method_m);
		$m2 = new Method("a", $this->method_m);
		$this->assertEquals(PartialOrder::SMALLER, $this->comparator->compare($m1, $m2));
	}

	public function testOverriddenMethodIsGreater() {
		$m1 = new Method("a", $this->method_m);
		$m2 = new Method("b", $this->method_m);
		$this->assertEquals(PartialOrder::GREATER, $this->comparator->compare($m1, $m2));
	}

	public function testOverriddenDeeperInChainMethodIsGreater() {
		$m1 = new Method("a", $this->method_m);
		$m2 = new Method("e", $this->method_m);
		$this->assertEquals(PartialOrder::GREATER, $this->comparator->compare($m1, $m2));
	}

	public function testSiblingsAreNotComparable() {
		$m1 = new Method("d", $this->method_m);
		$m2 = new Method("e", $this->method_m);
		$this->assertEquals(PartialOrder::NOT_COMPARABLE, $this->comparator->compare($m1, $m2));
	}

	public function testNonRelatedClassesAreNotComparable() {
		$m1 = new Method("a", $this->method_m);
		$m2 = new Method("f", $this->method_m);
		$this->assertEquals(PartialOrder::NOT_COMPARABLE, $this->comparator->compare($m1, $m2));
	}
}