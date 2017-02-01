<?php
namespace PhpExceptionFlow\Collection;

interface SetInterface {

	public function addEntry($element);

	public function unionWith(SetInterface $otherSet);

	public function differenceWith(SetInterface $otherSet);

	/**
	 * @return array of all the elements in this set
	 */
	public function evaluate();
}