<?php
namespace PhpExceptionFlow\CallGraphConstruction;

use PhpExceptionFlow\Collection\PartialOrderInterface;
use PHPTypes\State;

/**
 * Class ContractMethodResolver
 * this class can be used to build a map from methods defined in interfaces/abstract classes/classes to implementation
 * of that method further down in the class hierarchy.
 */
class OverridingMethodResolver implements MethodCallToMethodResolverInterface {

	/** @var State */
	private $state;

	public function __construct(State $state) {
		$this->state = $state;
	}

	/**
	 * @param PartialOrderInterface $partial_order
	 * @return Method[][][]
	 */
	public function fromPartialOrder(PartialOrderInterface $partial_order) {
		$queue = $partial_order->getMaximalElements();
		$class_method_map = [];
		$covered_methods = new \SplObjectStorage;

		while (empty($queue) === false) {
			/** @var Method $method */
			$method = array_shift($queue);
			$current_classlike = strtolower($method->getClass());
			$current_method_name = $method->getName();
			$current_classlike_resolves = $this->state->classResolves[$current_classlike];

			print sprintf("Now handling method %s->%s\n", $current_classlike, $current_method_name);

			//a method can be resolved by all subclasses that implement it, if it is not private
			if ($method->isPrivate() === false) {
				// an occurrence of this method can be resolved to any of its overriding classes
				$descendants = array_values(array_filter($partial_order->getDescendants($method), array(OverridingMethodResolver::class, 'methodIsImplemented')));

				if (isset($class_method_map[$current_classlike][$current_method_name]) === true) {
					$class_method_map[$current_classlike][$current_method_name] = array_merge($class_method_map[$current_classlike][$current_method_name], $descendants);
				} else {
					$class_method_map[$current_classlike][$current_method_name] = $descendants;
				}
			}

			if ($method->isImplemented() === true) {
				//this method also applies to all subclasses that do not implement it
				/** @var Method[] $overriding_methods */
				$overriding_methods = $partial_order->getChildren($method);

				$method_applies_to_subclasses = $this->state->classResolvedBy[$current_classlike];
				print $current_classlike . ": \n";
				print_r($method_applies_to_subclasses);

				foreach ($overriding_methods as $overriding_method) {
					//get the classes between the current class and the overriding method class:
					$overriding_class_resolved_by = $this->state->classResolvedBy[strtolower($overriding_method->getClass())];
					$method_applies_to_subclasses = array_diff($method_applies_to_subclasses, $overriding_class_resolved_by);
					print "after removing class " . $overriding_method->getClass() . "\n";
					print_r($method_applies_to_subclasses);
					print_r($this->state->classResolvedBy[$current_classlike]);
				}

				//print_r($method_applies_to_subclasses);


				foreach ($method_applies_to_subclasses as $applies_to_subclass) {
					if (isset($class_method_map[$applies_to_subclass][$current_method_name]) === true) {
						$class_method_map[$applies_to_subclass][$current_method_name][] = $method;
					} else {
						$class_method_map[$applies_to_subclass][$current_method_name] = [$method];
					}
				}
			}

			$covered_methods->attach($method);
			foreach ($partial_order->getChildren($method) as $child) {
				if ($covered_methods->contains($child) === false) {
					$queue[] = $child;
				}
			}
		}

		return $class_method_map;
	}

	/**
	 * @param Method $method
	 * @return bool
	 */
	private static function methodIsImplemented(Method $method) {
		return $method->isImplemented();
	}
}