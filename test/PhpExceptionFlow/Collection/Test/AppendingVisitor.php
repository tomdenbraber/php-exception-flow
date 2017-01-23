<?php
namespace PhpExceptionFlow\Collection\Test;

use PhpExceptionFlow\Collection\PartialOrder\PartialOrderVisitorInterface;

class AppendingVisitor implements PartialOrderVisitorInterface {

	public $element_stack = [];

	public function visitElement($element) {
		$this->element_stack[] = $element;
	}
}