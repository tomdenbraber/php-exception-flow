<?php

namespace PhpExceptionFlow\CallGraphConstruction;

use PhpExceptionFlow\Collection\PartialOrderInterface;

interface MethodCallToMethodResolverInterface {
	/**
	 * Calculate the set of methods that could be called for each possible combination of class/methods in a class
	 * hierarchy.
	 * @param PartialOrderInterface $partial_order
	 * @return Method[][][], use as [classname][methodname] possibly called methods
	 */
	public function fromPartialOrder(PartialOrderInterface $partial_order);
}