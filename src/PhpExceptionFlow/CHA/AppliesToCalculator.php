<?php
namespace PhpExceptionFlow\CHA;

use PhpExceptionFlow\Collection\PartialOrderInterface;
use PhpExceptionFlow\Collection\SetInterface;
use PhpExceptionFlow\Collection\Set\Set;

class AppliesToCalculator implements AppliesToCalculatorInterface {
	/** @var PartialOrderInterface partial_order */
	private $partial_order;
	private $class_resolved_by;

	public function __construct(PartialOrderInterface $partial_order, $class_resolves) {
		$this->partial_order = $partial_order;
		$this->class_resolved_by = $class_resolves;
	}

	/**
	 * calculates the applies_to set for a given method
	 * @param Method $method
	 * @return SetInterface
	 * @throws \UnexpectedValueException when the given method is not included in the partial order
	 */
	public function calculateAppliesTo(Method $method) {
		$overriding_methods = $this->partial_order->getChildren($method);
		$overriding_classes_cones = new Set();
		/** @var Method $overriding_method */
		foreach ($overriding_methods as $overriding_method) {
			$overriding_cone = new Set($this->class_resolved_by[$overriding_method->getClass()]);
			$overriding_classes_cones->unionWith($overriding_cone);
		}
		//standard initialized to the Cone of the class the method is defined in
		$method_applies_to = new Set($this->class_resolved_by[$method->getClass()]);

		$method_applies_to->differenceWith($overriding_classes_cones);
		return $method_applies_to;
	}
}