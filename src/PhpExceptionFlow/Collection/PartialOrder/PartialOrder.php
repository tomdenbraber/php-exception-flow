<?php
namespace PhpExceptionFlow\Collection\PartialOrder;

use PhpExceptionFlow\Collection\PartialOrderInterface;

class PartialOrder implements PartialOrderInterface, \JsonSerializable {
	/** @var ComparatorInterface */
	private $comparator;

	/** @var \SplObjectStorage */
	private $elements;

	/** @var  \SplObjectStorage */
	private $super_links;

	/** @var \SplObjectStorage */
	private $sub_links;

	public function __construct(ComparatorInterface $comparator) {
		$this->comparator = $comparator;
		$this->elements = new \SplObjectStorage();
		$this->super_links = new \SplObjectStorage();
		$this->sub_links = new \SplObjectStorage();
	}

	public function addElement(PartialOrderElementInterface $element_to_add) {
		if ($this->elements->contains($element_to_add) === true) {
			return;
		}

		$parents = array();
		foreach ($this->getMaximalElements() as $maximal_element) {
			$possible_parents = $this->getSmallestPossibleParents($element_to_add, $maximal_element);
			if ($possible_parents !== false) {
				$parents = array_merge($possible_parents, $parents);
			}
		}
		$added_parent = false;
		foreach ($parents as $parent) {
			$this->addRelationBetween($parent, $element_to_add);
			$added_parent = true;
		}
		$children = array();
		foreach ($this->getMinimalElements() as $minimal_element) {
			$possible_children = $this->getGreatestPossibleChildren($element_to_add, $minimal_element);
			if ($possible_children !== false) {
				$children = array_merge($possible_children, $children);
			}
		}

		$added_child = false;
		foreach ($children as $child) {
			$this->addRelationBetween($element_to_add, $child);
			$added_child = true;
			foreach ($this->getParents($child) as $childs_parent) {
				if ($this->comparator->compare($childs_parent, $element_to_add) === self::GREATER) {
					//childs_parent > element_to_add >= child
					$this->removeRelationBetween($childs_parent, $child);
				}
			}
		}

		if ($added_parent === false) {
			$this->super_links->attach($element_to_add, array());
		}

		if ($added_child === false) {
			$this->sub_links->attach($element_to_add, array());
		}
		$this->elements->attach($element_to_add);
	}

	public function removeElement(PartialOrderElementInterface $element) {
		if ($this->elements->contains($element) === true) {
			foreach ($this->super_links[$element] as $parent) {
				$parents_children = $this->sub_links[$parent];
				$pos = array_search($element, $parents_children, true);
				array_splice($parents_children, $pos, 1);
				$this->sub_links[$parent] = $parents_children;
			}

			foreach ($this->sub_links[$element] as $child) {
				$childrens_parent = $this->super_links[$child];
				$pos = array_search($element, $childrens_parent, true);
				array_splice($childrens_parent, $pos, 1);
				$this->super_links[$child] = $childrens_parent;
			}
			$this->elements->detach($element);
			$this->super_links->detach($element);
			$this->sub_links->detach($element);
		}
	}

	public function getLeastElement() {
		$minimal_elements = $this->getMinimalElements();
		if (count($minimal_elements) !== 1) {
			return null;
		} else {
			return $minimal_elements[0];
		}
	}

	/**
	 * @return mixed|null the element when there is one greatest element, else null
	 */
	public function getGreatestElement() {
		$maximal_elements = $this->getMaximalElements();
		if (count($maximal_elements) !== 1) {
			return null;
		} else {
			return $maximal_elements[0];
		}
	}

	/**
	 * @return array of the 'greatest' elements of this partial order
	 */
	public function getMaximalElements() {
		$maximal_elements = array();
		foreach ($this->super_links as $element) {
			if (empty($this->super_links[$element]) === true) {
				//this element has no parents and thus is maximal
				$maximal_elements[] = $element;
			}
		}
		return $maximal_elements;
	}

	/**
	 * @return array of the 'smallest' elements of this partial order
	 */
	public function getMinimalElements() {
		$minimal_elements = array();
		foreach ($this->sub_links as $element) {
			if (empty($this->sub_links [$element]) === true) {
				//this element has no children and thus is minimal
				$minimal_elements[] = $element;
			}
		}
		return $minimal_elements;
	}

	/**
	 * Retrieves the 'smallest' possible element from the partial order whioh is still greater than
	 * the given new_element, when we start looking at the given member_element
	 * @param PartialOrderElementInterface $new_element
	 * @param PartialOrderElementInterface $member_element
	 * @return mixed
	 */
	private function getSmallestPossibleParents(PartialOrderElementInterface $new_element, PartialOrderElementInterface $member_element) {
		$resulting_elems = [];
		$compare_res = $this->comparator->compare($new_element, $member_element);
		switch ($compare_res) {
			case self::NOT_COMPARABLE:
			case self::EQUAL:
			case self::GREATER;
				return false;
			case self::SMALLER:
				foreach ($this->sub_links[$member_element] as $smaller_element) {
					$smaller_parents = $this->getSmallestPossibleParents($new_element, $smaller_element);
					if ($smaller_parents !== false) {
						$resulting_elems[] = $smaller_element;
					}
				}
				if (empty($resulting_elems) === true) {
					$resulting_elems[] = $member_element;
				}
				break;
		}
		return $resulting_elems;
	}

	/**
	 * Retrieves the 'greatest' possible element from the partial order whioh is still smaller than
	 * the given new_element, when we start looking at the given member_element
	 * @param PartialOrderElementInterface $new_element
	 * @param PartialOrderElementInterface $member_element
	 * @return mixed
	 */
	private function getGreatestPossibleChildren(PartialOrderElementInterface $new_element, PartialOrderElementInterface $member_element) {
		$resulting_elems = [];
		$compare_res = $this->comparator->compare($new_element, $member_element);
		switch ($compare_res) {
			case self::NOT_COMPARABLE:
			case self::EQUAL:
			case self::SMALLER:
				return false;
			case self::GREATER;
				foreach ($this->super_links[$member_element] as $greater_element) {
					$greater_children = $this->getGreatestPossibleChildren($new_element, $greater_element);
					if ($greater_children !== false) {
						$resulting_elems[] = $greater_element;
					}
				}
				if (empty($resulting_elems) === true) {
					$resulting_elems[] = $member_element;
				}
				break;
		}
		return $resulting_elems;
	}


	/**
	 * @param PartialOrderElementInterface $element
	 * @throws \UnexpectedValueException when the given element is not in the partial order
	 * @return PartialOrderElementInterface[]
	 */
	public function getAncestors(PartialOrderElementInterface $element) {
		if ($this->elements->contains($element) === false) {
			throw new \UnexpectedValueException("No such element in this partial order.");
		}
		$parents = $this->getParents($element);
		$ancestors = $parents;
		foreach ($parents as $parent) {
			foreach ($this->getAncestors($parent) as $ancestor) {
				if (in_array($ancestor, $ancestors, true) === false) {
					$ancestors[] = $ancestor;
				}
			}
		}
		return $ancestors;
	}

	/**
	 * @param PartialOrderElementInterface $element
	 * @throws \UnexpectedValueException when the given element is not in the partial order
	 * @return PartialOrderElementInterface[]
	 */
	public function getParents(PartialOrderElementInterface $element) {
		if ($this->elements->contains($element) === false) {
			throw new \UnexpectedValueException("No such element in this partial order.");
		}
		return $this->super_links[$element];
	}

	/**
	 * @param PartialOrderElementInterface $element
	 * @throws \UnexpectedValueException when the given element is not in the partial order
	 * @return PartialOrderElementInterface[]
	 */
	public function getChildren(PartialOrderElementInterface $element) {
		if ($this->elements->contains($element) === false) {
			throw new \UnexpectedValueException("No such element in this partial order.");
		}
		return $this->sub_links[$element];
	}

	/**
	 * @param PartialOrderElementInterface $element
	 * @throws \UnexpectedValueException when the given element is not in the partial order
	 * @return PartialOrderElementInterface[]
	 */
	public function getDescendants(PartialOrderElementInterface $element) {
		if ($this->elements->contains($element) === false) {
			throw new \UnexpectedValueException("No such element in this partial order.");
		}
		$children = $this->getChildren($element);
		$descendants = $children;
		foreach ($children as $child) {
			foreach ($this->getDescendants($child) as $descendant) {
				if (in_array($descendant, $descendants, true) === false) {
					$descendants[] = $descendant;
				}
			}
		}
		return $descendants;
	}

	/**
	 * @return array with for each method/function its direct children and its direct parents
	 */
	public function jsonSerialize() {
		$result = [];
		foreach ($this->elements as $element) {
			$result[(string)$element]['ancestors'] = array_map(function($element) {
				return $element->jsonSerialize();
			}, $this->getAncestors($element));
			$result[(string)$element]['descendants'] = array_map(function($element) {
				return $element->jsonSerialize();
			}, $this->getDescendants($element));
		}
		return $result;
	}

	/**
	 * Removes the relationship between the two given elements
	 * @param PartialOrderElementInterface $greater
	 * @param PartialOrderElementInterface $smaller
	 */
	private function removeRelationBetween(PartialOrderElementInterface $greater, PartialOrderElementInterface $smaller) {
		$children = $this->sub_links[$greater];
		array_splice($children, array_search($smaller, $children, true), 1);
		$this->sub_links[$greater] = $children;

		$parents = $this->super_links[$smaller];
		array_splice($parents, array_search($greater, $parents, true), 1);
		$this->super_links[$smaller] = $parents;
	}

	/**
	 * Adds the relationship between the two given elements
	 * @param PartialOrderElementInterface $greater
	 * @param PartialOrderElementInterface $smaller
	 */
	private function addRelationBetween(PartialOrderElementInterface $greater, PartialOrderElementInterface $smaller) {
		if (isset($this->sub_links[$greater]) === false) {
			$this->sub_links[$greater] = array();
		}
		if (isset($this->super_links[$smaller]) === false) {
			$this->super_links[$smaller] = array();
		}

		$children = $this->sub_links[$greater];
		$children[] = $smaller;
		$this->sub_links[$greater] = $children;

		$parents = $this->super_links[$smaller];
		$parents[] = $greater;
		$this->super_links[$smaller] = $parents;
	}
}