<?php
namespace PhpExceptionFlow\CallGraphConstruction;

use PhpExceptionFlow\Collection\PartialOrder\ComparatorInterface;
use PhpExceptionFlow\Collection\PartialOrderInterface;
use PHPTypes\State;

class MethodComparator implements ComparatorInterface {
	/** @var State */
	private $state;

	public function __construct(State $state) {
		$this->state = $state;
	}

	/**
	 * @param Method $element1
	 * @param Method $element2
	 * @return int
	 * @throws \LogicException
	 */
	public function compare($element1, $element2) {
		if (($element1 instanceof Method && $element2 instanceof Method) === false) {
			throw new \LogicException("Please provide methods to the methodcomparator");
		}
		/**
		 * @var Method $element1
		 * @var Method $element2
		 */
		if ($element1->getName() !== $element2->getName()) {
			return PartialOrderInterface::NOT_COMPARABLE;
		} else if ($element1->getClass() === $element2->getClass()) {
			return PartialOrderInterface::EQUAL;
		} else {
			$element1_class_resolves = $this->state->classResolves[$element1->getClass()];
			$element2_class_resolves = $this->state->classResolves[$element2->getClass()];

			if (isset($element1_class_resolves[$element2->getClass()]) === true) {
				return PartialOrderInterface::SMALLER;
			} else if (isset($element2_class_resolves[$element1->getClass()]) === true) {
				return PartialOrderInterface::GREATER;
			} else {
				return PartialOrderInterface::NOT_COMPARABLE;
			}
		}
	}
}