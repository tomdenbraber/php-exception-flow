<?php
namespace PhpExceptionFlow\Collection;

use PhpExceptionFlow\Collection\PartialOrder\PartialOrderElementInterface;

interface PartialOrderInterface {
	public function addElement(PartialOrderElementInterface $element);

	public function removeElement(PartialOrderElementInterface $element);

	public function getMaximalElements();

	public function getMinimalElements();

	public function getGreatestElement();

	public function getLeastElement();

	public function getParents(PartialOrderElementInterface $element);

	public function getChildren(PartialOrderElementInterface $element);

	public function getAncestors(PartialOrderElementInterface $element);

	public function getDescendants(PartialOrderElementInterface $element);

	const NOT_COMPARABLE = 1;
	const SMALLER = 2;
	const EQUAL = 4;
	const GREATER = 8;
}