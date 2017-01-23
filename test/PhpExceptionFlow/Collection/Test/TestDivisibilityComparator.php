<?php
namespace PhpExceptionFlow\Collection\Test;

use PhpExceptionFlow\Collection\PartialOrder\ComparatorInterface;
use PhpExceptionFlow\Collection\PartialOrder\PartialOrder;

// this class is used for testing the PartialOrder
class TestDivisibilityComparator implements ComparatorInterface {
	public function compare($element1, $element2) {
		if (($element1 instanceof Number && $element2 instanceof Number) === false) {
			throw new \LogicException("Wrong type submitted to comparator");
		}

		$element1 = $element1->value;
		$element2 = $element2->value;


		if ($element1 === 0) {
			return PartialOrder::GREATER;
		} else if ($element2 === 0) {
			return PartialOrder::SMALLER;
		}

		$el1_div_el2 = $element1 / $element2;
		$el2_div_el1 = $element2 / $element1;

		if (is_int($el1_div_el2) === true && is_int($el2_div_el1) === true) {
			return PartialOrder::EQUAL;
		} else if (is_int($el1_div_el2) === true) {
			return PartialOrder::GREATER;
		} else if (is_int($el2_div_el1) === true) {
			return PartialOrder::SMALLER;
		} else {
			return PartialOrder::NOT_COMPARABLE;
		}
	}

}