<?php
namespace PhpExceptionFlow\CHA;

class Set implements SetInterface {

	/** @var array */
	private $elements = [];
	/** @var int[] */
	private $stacked_operations = [];
	/** @var SetInterface[] */
	private $stacked_sets = [];

	const DIFFERENCE = 1;
	const UNION = 2;

	public function __construct(array $elements = []) {
		$this->elements = $elements;
		$this->union = [];
		$this->difference = [];
	}

	public function addEntry($element) {
		$this->unionWith(new Set([$element]));
	}

	public function differenceWith(SetInterface $otherSet) {
		$this->stacked_sets[] = $otherSet;
		$this->stacked_operations[] = self::DIFFERENCE;
	}

	public function unionWith(SetInterface $otherSet) {
		$this->stacked_sets[] = $otherSet;
		$this->stacked_operations[] = self::UNION;
	}

	public function evaluate() {
		$elements = $this->elements;

		foreach ($this->stacked_operations as $i => $stacked_operation) {
			$current_set = $this->stacked_sets[$i];
			if ($stacked_operation === self::UNION) {
				$elements = array_unique(array_merge($elements, $current_set->evaluate()));
			} else if ($stacked_operation === self::DIFFERENCE) {
				$elements = array_diff($elements, $current_set->evaluate());
			}
		}
		return array_values($elements);
	}
}