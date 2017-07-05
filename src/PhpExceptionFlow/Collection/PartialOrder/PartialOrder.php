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

	private $maximal_elements;
	private $minimal_elements;

	public function __construct(ComparatorInterface $comparator) {
		$this->comparator = $comparator;
		$this->elements = new \SplObjectStorage();
		$this->super_links = new \SplObjectStorage();
		$this->sub_links = new \SplObjectStorage();
		$this->maximal_elements = new \SplObjectStorage();
		$this->minimal_elements = new \SplObjectStorage();
	}

	public function addElement(PartialOrderElementInterface $element) {
		if ($this->elements->contains($element) === true) {
			return;
		}

		$this->elements->attach($element);
		$this->super_links->attach($element, array());
		$this->sub_links->attach($element, array());

		$maximal_elements = $this->getMaximalElements();

		$this->maximal_elements->attach($element);

		foreach ($maximal_elements as $maximal_element) {
			$comparison = $this->comparator->compare($element, $maximal_element);
			if ($comparison === self::SMALLER) {
				$this->insertElementBeneath($element, $maximal_element);
			} else if ($comparison === self::GREATER) {
				$this->insertElementAbove($element, $maximal_element);
			}
		}

		$minimal_elements = $this->getMinimalElements();

		$this->minimal_elements->attach($element);
		foreach ($minimal_elements as $minimal_element) {
			$comparison = $this->comparator->compare($element, $minimal_element);
			if ($comparison === self::SMALLER) {
				$this->insertElementBeneath($element, $minimal_element);
			} else if ($comparison === self::GREATER) {
				$this->insertElementAbove($element, $minimal_element);
			}
		}
	}

	private function insertElementBeneath(PartialOrderElementInterface $element, PartialOrderElementInterface $ancestor) {
		$children = $this->getChildren($ancestor);
		$child_selected = false;
		foreach ($children as $child) {
			$comparison = $this->comparator->compare($element, $child);
			if ($comparison === self::GREATER) {
				$child_selected = true;
				$this->insertElementBetween($element, $ancestor, $child);
			} elseif ($comparison === self::SMALLER) {
				$child_selected = true;
				$this->insertElementBeneath($element, $child);
			}
		}
		if ($child_selected === false) {
			$this->addRelationBetween($ancestor, $element);
		}

	}

	private function insertElementAbove(PartialOrderElementInterface $element, PartialOrderElementInterface $descendant) {
		$parents = $this->getParents($descendant);
		$child_selected = false;
		foreach ($parents as $parent) {
			$comparison = $this->comparator->compare($element, $parent);
			if ($comparison === self::GREATER) {
				$this->insertElementAbove($element, $parent);
			} elseif ($comparison === self::SMALLER) {
				$this->insertElementBetween($element, $parent, $descendant);
			}
		}
		if ($child_selected === false) {
			$this->addRelationBetween($element, $descendant);
		}

	}

	private function insertElementBetween($element, $parent, $child) {
		$this->addRelationBetween($element, $child);
		$this->addRelationBetween($parent, $element);
		$this->removeRelationBetween($parent, $child);
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
		$max_els = [];
		foreach ($this->maximal_elements as $obj) {
			$max_els[] = $obj;
		}
		return $max_els;
	}

	/**
	 * @return array of the 'smallest' elements of this partial order
	 */
	public function getMinimalElements() {
		$min_els = [];
		foreach ($this->minimal_elements as $obj) {
			$min_els[] = $obj;
		}
		return $min_els;
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
			$result = array_merge($element->jsonSerialize(), $result);

			$result[(string)$element]['ancestors'] = [];
			foreach ($this->getAncestors($element) as $ancestor) {
				$result[(string)$element]['ancestors'][(string)$ancestor] = (string)$ancestor;
			}

			$result[(string)$element]['descendants'] = [];
			foreach ($this->getDescendants($element) as $descendant) {
				$result[(string)$element]['descendants'][(string)$descendant] = (string)$descendant;
			}
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
		/** @var PartialOrderElementInterface[] $children */
		$children = $this->sub_links[$greater];
		if (in_array($smaller, $children, true) === false) {
			$children[] = $smaller;
			$this->sub_links[$greater] = $children;
		}

		/** @var PartialOrderElementInterface[] $parents */
		$parents = $this->super_links[$smaller];
		if (in_array($greater, $parents, true) === false) {
			$parents[] = $greater;
			$this->super_links[$smaller] = $parents;
		}

		if ($this->minimal_elements->contains($greater) === true) {
			$this->minimal_elements->detach($greater);
		}

		if ($this->maximal_elements->contains($smaller) === true) {
			$this->maximal_elements->detach($smaller);
		}
	}
}