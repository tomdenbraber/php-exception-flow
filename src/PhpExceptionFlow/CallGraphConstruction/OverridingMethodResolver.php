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

			//rules:
			// 1. a method that is implemented always resolves itself
			// 2. a method can be resolved by any of the implementations of subclasses/implementations
			// 3. a class which does not implement a method can resolve to the first implementing superclass
			//      -> can be rephrased as: a method also applies to all subclasses between this class and the next class which implement the method
			// 4. a class which does not implement a method can resolve to any of the implementations in the subclasses

			//R1:
			if ($method->isImplemented() === true) {
				$class_method_map = $this->addToClassMethodMap($class_method_map, $method, $method->getClass());
			}

			/** @var Method[] $child_methods */
			$child_methods = $partial_order->getChildren($method);

			//R3
			//gather all subclasses between this class and the end of the hierarchy/the next class implementing this method
			if ($method->isPrivate() === false) {
				$subclasses_not_implementing = $this->state->classResolvedBy[$current_classlike];
				unset($subclasses_not_implementing[$current_classlike]);
				foreach ($child_methods as $child) {
					$child_resolved_by = $this->state->classResolvedBy[strtolower($child->getClass())];
					$subclasses_not_implementing = array_diff($subclasses_not_implementing, $child_resolved_by);
				}
				foreach ($subclasses_not_implementing as $subclass) {
					print sprintf("R3: covering %s, adding method %s.%s\n", $subclass, $method->getClass(), $method->getName());
					$class_method_map = $this->addToClassMethodMap($class_method_map, $method, $subclass);
				}
			}

			//R2, R4: reasoned from the current method upwards; add this method to all classes in
			// between the current class and the highest implementing class in the hierarchy
			if ($method->isPrivate() === false && $method->isImplemented() === true) {
				$ancestors = $partial_order->getAncestors($method);
				$current_classlike_resolves = $this->state->classResolves[$current_classlike];
				unset($current_classlike_resolves[$current_classlike]);
				$current_classlike_resolves = array_keys($current_classlike_resolves);
				$super_classes = [];
				foreach ($ancestors as $ancestor) {
					$ancestor_resolved_by = array_keys($this->state->classResolvedBy[strtolower($ancestor->getClass())]);
					$path_to_ancestor = array_intersect($ancestor_resolved_by, $current_classlike_resolves);
					if (empty($path_to_ancestor) === true) {
						//no path discovered from $method to $ancestor via the class resolves shizzle. probably a trait.
						//because there is a relation in the partial order, just add the ancestor to the super classes
						$path_to_ancestor[] = strtolower($ancestor->getClass());
					}
					$super_classes = array_merge($super_classes, $path_to_ancestor);
				}

				$super_classes = array_unique($super_classes);
				foreach ($super_classes as $super_class) {
					print sprintf("R2, R4: covering %s, adding method %s.%s\n", $super_class, $method->getClass(), $method->getName());
					$class_method_map = $this->addToClassMethodMap($class_method_map, $method, $super_class);
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

	private function addToClassMethodMap($class_method_map, Method $method, $class) {
		$class = strtolower($class);
		if (isset($class_method_map[$class]) === false) {
			$class_method_map[$class] = [
				$method->getName() => [$method],
			];
		} else {
			if (isset($class_method_map[$class][$method->getName()]) === false) {
				$class_method_map[$class][$method->getName()] = [$method];
			} else {
				$class_method_map[$class][$method->getName()][] = $method;
			}
		}
		return $class_method_map;
	}
}