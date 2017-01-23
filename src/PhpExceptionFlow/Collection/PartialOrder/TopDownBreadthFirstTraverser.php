<?php
namespace PhpExceptionFlow\Collection\PartialOrder;
use PhpExceptionFlow\Collection\PartialOrderInterface;

class TopDownBreadthFirstTraverser implements PartialOrderTraverserInterface {
	/** @var PartialOrderVisitorInterface[] $visitors */
	private $visitors = [];

	public function addVisitor(PartialOrderVisitorInterface $visitor) {
		$this->visitors[] = $visitor;
	}

	/**
	 * @param PartialOrderInterface $partial_order
	 */
	public function traverse(PartialOrderInterface $partial_order) {
		$already_queued = new \SplObjectStorage;
		$element_queue = $partial_order->getMaximalElements();
		while (empty($element_queue) === false) {
			$current_el = array_shift($element_queue);
			foreach ($partial_order->getChildren($current_el) as $child) {
				if ($already_queued->contains($child) === false) {
					$element_queue[] = $child;
					$already_queued->attach($child);
				}
			}
			foreach ($this->visitors as $visitor) {
				$visitor->visitElement($current_el);
			}

		}
	}
}