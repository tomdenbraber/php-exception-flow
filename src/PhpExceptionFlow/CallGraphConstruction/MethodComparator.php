<?php
namespace PhpExceptionFlow\CallGraphConstruction;

use PhpExceptionFlow\Collection\PartialOrder\ComparatorInterface;
use PhpExceptionFlow\Collection\PartialOrder\PartialOrder;
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
			if (($this->state->isClass($element1->getClass()) || $this->state->isInterface($element1->getClass())) &&
				($this->state->isClass($element2->getClass()) || $this->state->isInterface($element2->getClass()))
			) {
				return $this->compareClassMethods($element1, $element2);
			} else if ($this->state->isTrait($element1->getClass()) &&
				($this->state->isClass($element2->getClass()) || $this->state->isInterface($element2->getClass()))) {
				return $this->compareTraitAndClassMethod($element1, $element2);
			} else if (($this->state->isClass($element1->getClass()) || $this->state->isInterface($element1->getClass())) &&
				$this->state->isTrait($element2->getClass())) {
				return $this->invert($this->compareTraitAndClassMethod($element2, $element1));
			} else if ($this->state->isTrait($element1->getClass()) && $this->state->isTrait($element2->getClass())) {
				// todo: this will result in an error, except if the programmer indicated preference for a certain type within a class.
				// but this preference can differ per class, so it is not an easy thing to add; my guess is that it does not occur that often.
				return PartialOrder::NOT_COMPARABLE;
			}
		}
	}

	private function compareClassMethods(Method $element1, Method $element2) {
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

	private function compareTraitAndClassMethod(Method $trait_method, Method $class_method) {
		$trait_resolves = $this->state->classResolves[$trait_method->getClass()];
		$class_resolves = $this->state->classResolves[$class_method->getClass()];

		if (isset($trait_resolves[$class_method->getClass()]) === true) {
			return PartialOrderInterface::SMALLER;
		} else if (isset($class_resolves[$trait_method->getClass()]) === true) {
			return PartialOrderInterface::GREATER;
		} else {
			// if a subclass of the class resolves to the trait, the trait method is smaller.
			foreach ($this->state->classResolvedBy[$trait_method->getClass()] as $class_using_trait) {
				if (isset($this->state->classResolvedBy[$class_method->getClass()][$class_using_trait]) === true) {
					return PartialOrderInterface::SMALLER;
				} else if (isset($this->state->classResolvedBy[$class_using_trait][$class_method->getClass()]) === true) {
					return PartialOrder::GREATER;
				}
			}
			return PartialOrderInterface::NOT_COMPARABLE;
		}
	}

	private function compareTraitMethods(Method $trait_method1, Method $trait_method2) {

	}

	private function invert(int $comparison_outcome) {
		if ($comparison_outcome === PartialOrder::GREATER) {
			return PartialOrder::SMALLER;
		} else if ($comparison_outcome === PartialOrder::SMALLER) {
			return PartialOrder::GREATER;
		} else {
			return $comparison_outcome;
		}
	}
}