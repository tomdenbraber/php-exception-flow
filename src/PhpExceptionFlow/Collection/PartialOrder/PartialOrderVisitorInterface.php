<?php
namespace PhpExceptionFlow\Collection\PartialOrder;

interface PartialOrderVisitorInterface {
	/**
	 * visit an element in the partial order
	 * @param $element
	 */
	public function visitElement($element);
}