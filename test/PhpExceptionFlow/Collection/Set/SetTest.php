<?php
namespace PhpExceptionFlow\Collection\Set;

class SetTest extends \PHPUnit_Framework_TestCase {

	public function testEvaluateWithEmptyIsEmpty() {
		$set = new Set();
		$this->assertEmpty($set->evaluate());
	}

	public function testEvaluateSimpleSetReturnsElements() {
		$set = new Set(array("a", "b", "c"));
		$this->assertEquals(array("a", "b", "c"), $set->evaluate());
	}

	public function testEvaluateOnUnionReturnsUniqueElementsFromUnion() {
		$set = new Set(array("a", "b", "c"));
		$union_set = new Set(array("b", "d", "e"));
		$set->unionWith($union_set);

		$this->assertEquals(array("a", "b", "c", "d", "e"), $set->evaluate());
	}

	public function testEvaluateOnDifferenceReturnsCorrectSet() {
		$set = new Set(array("a", "b", "c"));
		$diff_set = new Set(array("b", "d", "e"));
		$set->differenceWith($diff_set);

		$this->assertEquals(array("a", "c"), $set->evaluate());
	}

	public function testWithNestedUnionAndDifferenceReturnsCorrectSet() {
		$base_set = new Set(array("a", "b", "c"));

		$first_union = new Set(array("c", "d"));
		$second_union = new Set(array("x", "y", "z"));

		$diff = new Set(array("a", "x"));

		$base_set->unionWith($first_union);
		$first_union->unionWith($second_union);
		$second_union->differenceWith($diff);

		$this->assertEquals(array("a", "b", "c", "d", "y", "z"), $base_set->evaluate());
	}

	public function testCorrectOrderOfOperations() {
		$base = new Set(array("a", "b", "c"));
		$difference = new Set(array("c", "d"));
		$union = new Set(array("d", "e"));
		$base->differenceWith($difference);
		$base->unionWith($union);

		$this->assertEquals(array("a", "b", "d", "e"), $base->evaluate());
	}
}