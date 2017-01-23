<?php
namespace PhpExceptionFlow\Collection;

interface PartialOrderInterface {
	public function addElement($element);

	public function removeElement($element);

	public function getMaximalElements();

	public function getMinimalElements();

	public function getGreatestElement();

	public function getLeastElement();

	public function getParents($element);

	public function getChildren($element);

	public function getAncestors($element);

	public function getDescendants($element);

	const NOT_COMPARABLE = 1;
	const SMALLER = 2;
	const EQUAL = 4;
	const GREATER = 8;
}