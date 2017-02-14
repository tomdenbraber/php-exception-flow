<?php

namespace PhpExceptionFlow\CallGraphConstruction;
use PhpExceptionFlow\Collection\PartialOrderInterface;
use PhpExceptionFlow\Collection\Set\Set;

/**
 * Class ChaMethodResolver
 * CHA = Class Hierarchy Analysis.
 * This class can be used to calculate which method calls should be resolved to which methods in the class hierarchy.
 * It makes use of the 'applies-to' paradigm by Dean, Grove as described in
 * 'Optimization of Object-Oriented Programs Using Static Class Hierarchy Analysis'
 */
class AppliesToMethodResolver implements MethodCallToMethodResolverInterface {

	/** @var array */
	private $class_resolved_by;

	public function __construct(array $class_resolved_by) {
		$this->class_resolved_by = $class_resolved_by;
	}

	public function fromPartialOrder(PartialOrderInterface $partial_order) {
		$class_method_to_method = [];
		$queue = $partial_order->getMaximalElements();
		while (empty($queue) === false) {
			/** @var Method $method */
			$method = array_shift($queue);
			$method_applies_to = $this->calculateAppliesTo($method, $partial_order);
			foreach ($method_applies_to as $class) {
				if (isset($class_method_to_method[$class]) === true) {
					$class_method_to_method[$class][$method->getName()][] = $method;
				} else {
					$class_method_to_method[$class] = [
						$method->getName() => [$method],
					];
				}
			}
			$overriding_methods = $partial_order->getChildren($method);
			foreach ($overriding_methods as $overriding_method) {
				if (in_array($overriding_method, $queue, true) === false) {
					$queue[] = $overriding_method;
				}
			}
		}
		return $class_method_to_method;
	}


	/**
	 * @param Method $method
	 * @param PartialOrderInterface $partial_order
	 * @return array
	 */
	private function calculateAppliesTo(Method $method, PartialOrderInterface $partial_order) {
		if ($method->isImplemented() === false) {
			return [];
		} else if ($method->isPrivate() === true) { //only applies to itself
			return [strtolower($method->getClass())];
		} else {
			$overriding_methods = $partial_order->getChildren($method);
			$overriding_classes_cones = new Set();
			/** @var Method $overriding_method */
			foreach ($overriding_methods as $overriding_method) {
				$overriding_cone = new Set($this->class_resolved_by[$overriding_method->getClass()]);
				$overriding_classes_cones->unionWith($overriding_cone);
			}
			//standard initialized to the Cone of the class the method is defined in
			$method_applies_to = new Set($this->class_resolved_by[$method->getClass()]);
			$method_applies_to->differenceWith($overriding_classes_cones);
			return $method_applies_to->evaluate();
		}
	}
}