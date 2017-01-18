<?php
namespace PhpExceptionFlow\CHA;

interface ComparatorInterface {

	/**
	 * @param $element1
	 * @param $element2
	 * @return int
	 * @throws \LogicException when elements are provided that this comparator cannot handle
	 */
	public function compare($element1, $element2);
}