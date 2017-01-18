<?php
namespace PhpExceptionFlow\CHA;

class MethodComparator implements ComparatorInterface {
	/** @var array */
	private $class_resolves;

	public function __construct(array $class_resolves) {
		$this->class_resolves = $class_resolves;
	}

	public function compare($element1, $element2) {
		if (($element1 instanceof Method && $element2 instanceof Method) === false) {
			throw new \LogicException("Please provide methods to the methodcomparator");
		}
		/**
		 * @var $element1 Method
		 * @var $element2 Method
		 */
		if ($element1->getName() !== $element2->getName()) {
			return PartialOrder::NOT_COMPARABLE;
		} else if ($element1->getClass() === $element2->getClass()) {
			return PartialOrder::EQUAL;
		} else {
			$element1_class_resolves = $this->class_resolves[$element1->getClass()];
			$element2_class_resolves = $this->class_resolves[$element2->getClass()];

			if (isset($element1_class_resolves[$element2->getClass()]) === true) {
				return PartialOrder::SMALLER;
			} else if (isset($element2_class_resolves[$element1->getClass()]) === true) {
				return PartialOrder::GREATER;
			} else {
				return PartialOrder::NOT_COMPARABLE;
			}
		}
	}
}