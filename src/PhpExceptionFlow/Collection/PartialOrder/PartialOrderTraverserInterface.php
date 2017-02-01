<?php
namespace PhpExceptionFlow\Collection\PartialOrder;

use PhpExceptionFlow\Collection\PartialOrderInterface;

interface PartialOrderTraverserInterface {
	public function traverse(PartialOrderInterface $partial_order);

	public function addVisitor(PartialOrderVisitorInterface $visitor);
}