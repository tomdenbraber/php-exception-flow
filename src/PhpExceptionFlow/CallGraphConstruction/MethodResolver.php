<?php
namespace PhpExceptionFlow\CallGraphConstruction;

use PhpExceptionFlow\Collection\PartialOrderInterface;
use PHPTypes\State;

/**
 * Class ContractMethodResolver
 * this class can be used to build a map from methods defined in interfaces/abstract classes/classes to implementation
 * of that method further down in the class hierarchy.
 */
class MethodResolver implements MethodCallToMethodResolverInterface {

	/** @var State */
	private $state;

	/** @var Method[][][] */
	private $class_method_map;

	public function __construct(State $state) {
		$this->state = $state;
	}

	/**
	 * @param PartialOrderInterface $partial_order
	 * @return Method[][][]
	 */
	public function fromPartialOrder(PartialOrderInterface $partial_order) {
		$this->class_method_map = [];
		$queue = $partial_order->getMaximalElements();
		$covered_methods = new \SplObjectStorage;

		while (empty($queue) === false) {
			/** @var Method $method */
			$method = array_shift($queue);

			//rules:
			// 1. a method that is implemented always resolves itself
			// 2. a method can be resolved by any of the implementations of subclasses/implementations
			// 3. a class which does not implement a method can resolve to the first implementing superclass
			//      -> can be rephrased as: a method also applies to all subclasses between this class and the next class which implement the method
			// 4. a class which does not implement a method can resolve to any of the implementations in the subclasses

			//R1:
			if ($method->isImplemented() === true) {
				$this->resolveSelf($method);
				if ($method->isPrivate() === false) {
					// R3: adds this method to all classes which are between this implementation and the next implementation down in the hierarchy
					// (this is the applies-to calculation)
					$this->resolveInheritedMethod($method, $partial_order);
					// R2, R4 reasoned from the current method upwards; add this method to all classes in
					// between the current class and the highest implementing class in the hierarchy
					$this->resolveMethodsSubsitutionPrinciple($method, $partial_order);
				}
			}

			$covered_methods->attach($method);
			foreach ($partial_order->getChildren($method) as $child) {
				if ($covered_methods->contains($child) === false) {
					$queue[] = $child;
				}
			}
		}

		return $this->class_method_map;
	}

	/**
	 * @param Method $method
	 * @param string $class
	 */
	private function addToClassMethodMap(Method $method, string $class) {
		$class = strtolower($class);
		if (isset($this->class_method_map[$class]) === false) {
			$this->class_method_map[$class] = [
				$method->getName() => [$method],
			];
		} else {
			if (isset($this->class_method_map[$class][$method->getName()]) === false) {
				$this->class_method_map[$class][$method->getName()] = [$method];
			} else {
				$this->class_method_map[$class][$method->getName()][] = $method;
			}
		}
	}

	private function resolveSelf(Method $method) {
		$this->addToClassMethodMap($method, $method->getClass());
	}

	/**
	 * @param Method $method
	 * @param PartialOrderInterface $partial_order
	 */
	private function resolveInheritedMethod(Method $method, PartialOrderInterface $partial_order) {
		/** @var Method[] $child_methods */
		$child_methods = $partial_order->getChildren($method);
		$current_classlike = strtolower($method->getClass());

		if (isset($this->state->classResolvedBy[$current_classlike]) === false) {
			print sprintf("%s is not registered in State, so it will be skipped(R3).\n", $current_classlike);
			return;
		}

		$subclasses_not_implementing = $this->state->classResolvedBy[$current_classlike];
		unset($subclasses_not_implementing[$current_classlike]);
		foreach ($child_methods as $child) {
			$child_resolved_by = $this->state->classResolvedBy[strtolower($child->getClass())];
			$subclasses_not_implementing = array_diff($subclasses_not_implementing, $child_resolved_by);
		}

		foreach ($subclasses_not_implementing as $subclass) {
			print sprintf("R3: covering %s, adding method %s.%s\n", $subclass, $method->getClass(), $method->getName());
			$this->addToClassMethodMap($method, $subclass);
		}
	}

	/**
	 * @param Method $method
	 * @param PartialOrderInterface $partial_order
	 */
	private function resolveMethodsSubsitutionPrinciple(Method $method, PartialOrderInterface $partial_order) {
		/** @var Method[] $ancestors */
		$ancestors = $partial_order->getAncestors($method);
		$current_classlike = strtolower($method->getClass());

		if (isset($this->state->classResolvedBy[$current_classlike]) === false) {
			print sprintf("%s is not registered in State, so it will be skipped(R2, R4).\n", $current_classlike);
			return;
		}

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
			$this->addToClassMethodMap($method, $super_class);
		}
	}
}