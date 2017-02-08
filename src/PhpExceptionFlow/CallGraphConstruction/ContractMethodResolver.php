<?php
namespace PhpExceptionFlow\CallGraphConstruction;

use PhpExceptionFlow\Collection\PartialOrderInterface;

/**
 * Class ContractMethodResolver
 * this class can be used to build a map from methods defined in interfaces/abstract classes to actual implementations
 * of that method with regards to the class hierarchy.
 */
class ContractMethodResolver implements MethodCallToMethodResolverInterface {

	/**
	 * @param PartialOrderInterface $partial_order
	 * @return Method[][][]
	 */
	public function fromPartialOrder(PartialOrderInterface $partial_order) {
		$applicable_methods = [];
		$queue = $partial_order->getMaximalElements();
		while (empty($queue) === false) {
			/** @var Method $method */
			$method = array_shift($queue);
			if ($method->isImplemented() === false) {
				$applicable_methods[strtolower($method->getClass())][strtolower($method->getName())] = $this->resolve($method, $partial_order);
				// queue all children, they might be unimplemented too
				foreach ($partial_order->getChildren($method) as $child) {
					if (in_array($child, $queue, true) === false) {
						$queue[] = $child;
					}
				}
			}
		}
		return $applicable_methods;
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
	private function methodIsImplemented(Method $method) {
		return $method->isImplemented();
	}
}