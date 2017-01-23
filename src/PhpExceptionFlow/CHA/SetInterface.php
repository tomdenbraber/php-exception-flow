<?php
namespace PhpExceptionFlow\CHA;

interface SetInterface {

	public function addEntry($element);

	public function unionWith(SetInterface $otherSet);

	public function differenceWith(SetInterface $otherSet);

	/**
	 * @return array of all the classes in this set
	 */
	public function evaluate();
}