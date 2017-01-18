<?php
namespace PhpExceptionFlow\Test\CHA;

use PhpExceptionFlow\CHA\MethodComparator;
use PhpExceptionFlow\CHA\Method;
use PhpExceptionFlow\CHA\PartialOrder;

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

	public function setUp() {
		$this->comparator = new MethodComparator($this->resolves);
	}

	public function testMethodsWithDifferentNamesAreNotComparable() {
		$m1 = new Method("a", "m", array());
		$m2 = new Method("a", "f", array());
		$this->assertEquals(PartialOrder::NOT_COMPARABLE, $this->comparator->compare($m1, $m2));
	}

	public function testMethodsWithSameNamesAndSameClassAreEqual() {
		$m1 = new Method("a", "m", array());
		$m2 = new Method("a", "m", array());
		$this->assertEquals(PartialOrder::EQUAL, $this->comparator->compare($m1, $m2));
	}

	public function testOverridingMethodIsSmaller() {
		$m1 = new Method("b", "m", array());
		$m2 = new Method("a", "m", array());
		$this->assertEquals(PartialOrder::SMALLER, $this->comparator->compare($m1, $m2));
	}

	public function testOverriddenMethodIsGreater() {
		$m1 = new Method("a", "m", array());
		$m2 = new Method("b", "m", array());
		$this->assertEquals(PartialOrder::GREATER, $this->comparator->compare($m1, $m2));
	}

	public function testOverriddenDeeperInChainMethodIsGreater() {
		$m1 = new Method("a", "m", array());
		$m2 = new Method("e", "m", array());
		$this->assertEquals(PartialOrder::GREATER, $this->comparator->compare($m1, $m2));
	}

	public function testSiblingsAreNotComparable() {
		$m1 = new Method("d", "m", array());
		$m2 = new Method("e", "m", array());
		$this->assertEquals(PartialOrder::NOT_COMPARABLE, $this->comparator->compare($m1, $m2));
	}

	public function testNonRelatedClassesAreNotComparable() {
		$m1 = new Method("a", "m", array());
		$m2 = new Method("f", "m", array());
		$this->assertEquals(PartialOrder::NOT_COMPARABLE, $this->comparator->compare($m1, $m2));
	}
}