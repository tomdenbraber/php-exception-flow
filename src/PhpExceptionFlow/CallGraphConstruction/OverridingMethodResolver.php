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
			$current_classlike_resolves = $this->state->classResolves[$current_classlike];

			if ($method->isPrivate() === false) {
				$descendants = array_values(array_filter($partial_order->getDescendants($method), array(OverridingMethodResolver::class, 'methodIsImplemented')));

				// an occurrence of this method can be resolved to any of its overriding classes
				if (isset($class_method_map[strtolower($method->getClass())][$method->getName()]) === true) {
					$class_method_map[strtolower($method->getClass())][$method->getName()] = array_merge(
						$class_method_map[strtolower($method->getClass())][$method->getName()], $descendants
					);
				} else {
					$class_method_map[strtolower($method->getClass())][$method->getName()] = $descendants;
				}
			} else {
				$class_method_map[strtolower($method->getClass())][$method->getName()] = [];
			}

			// all non-implementing parent classes of current_classlike between the next implementation up in the hierarchy
			// and the current class can be resolved to the currently handled method
			foreach ($partial_order->getParents($method) as $child) {
				$parent_resolved_by = $this->state->classResolvedBy[strtolower($child->getClass())];
				$in_between_classes = array_diff(array_intersect($parent_resolved_by, $current_classlike_resolves), [$current_classlike, $child->getClass()]);
				foreach ($in_between_classes as $between_class) {
					if (isset($class_method_map[$between_class][$method->getName()]) === true) {
						$class_method_map[$between_class][$method->getName()] = [$method];
					} else {
						$class_method_map[$between_class][$method->getName()][] = $method;
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
	 * @param PartialOrderInterface $partial_order
	 * @return Method[]
	 */
	private function resolve(Method $method, PartialOrderInterface $partial_order) {
		return array_values(array_filter($partial_order->getDescendants($method), array($this, 'methodIsImplemented')));
	}


	/**
	 * @param Method $method
	 * @return bool
	 */
	private static function methodIsImplemented(Method $method) {
		return $method->isImplemented();
	}
}