<?php
namespace PhpExceptionFlow\CHA;
use PhpExceptionFlow\Collection\PartialOrder\PartialOrderVisitorInterface;

class AppliesToVisitor implements PartialOrderVisitorInterface {

	/** @var AppliesToCalculatorInterface $partial_order */
	private $applies_to_calculator;

	/**
	 * @var array $class_to_method_map
	 */
	private $class_to_method_map = [];


	public function __construct(AppliesToCalculatorInterface $applies_to_calculator) {
		$this->applies_to_calculator = $applies_to_calculator;
	}

	/**
	 * @param Method $method
	 */
	public function visitElement($method) {
		$method_applies_to = $this->applies_to_calculator->calculateAppliesTo($method)->evaluate();

		foreach ($method_applies_to as $class) {
			if (isset($this->class_to_method_map[$class]) === false) {
				$this->class_to_method_map[$class] = [];
			}
			$this->class_to_method_map[$class][$method->getName()] = $method;
		}
	}

	public function getClassToMethodMap() {
		return $this->class_to_method_map;
	}
}