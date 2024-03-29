<?php
namespace PhpExceptionFlow\FlowCalculator;

use PhpExceptionFlow\Scope\Scope;
use PhpExceptionFlow\Scope\ScopeTraverserInterface;
use PhpExceptionFlow\Scope\ScopeVisitor\CalculatorWrappingVisitor;

class TraversingCalculator implements TraversingCalculatorInterface {

	/** @var ScopeTraverserInterface $traverser */
	private $traverser;
	/** @var CalculatorWrappingVisitor $visitor */
	private $visitor;
	/** @var FlowCalculatorInterface $wrapped_calculator */
	protected $wrapped_calculator;

	/**
	 * TraversingCalculator constructor.
	 * @param ScopeTraverserInterface $traverser
	 * @param CalculatorWrappingVisitor $visitor
	 * @param FlowCalculatorInterface $wrapped_calculator; this calculator has to be wrapped by a CalculatorWrappingVisitor which is inserted into the given ScopeTraverser
	 */
	public function __construct(ScopeTraverserInterface $traverser, CalculatorWrappingVisitor $visitor, FlowCalculatorInterface $wrapped_calculator = null) {
		$this->traverser = $traverser;
		$this->visitor = $visitor;
		if ($wrapped_calculator !== null) {
			$this->wrapped_calculator = $wrapped_calculator;
		}
	}

	/**
	 * @param FlowCalculatorInterface $exception_set_calculator
	 * @throws \LogicException
	 */
	public function addCalculator(FlowCalculatorInterface $exception_set_calculator) {
		if ($this->wrapped_calculator !== null) {
			throw new \LogicException(sprintf("Cannot wrap calculator with type %s; already wrapped calculator with type %s", $exception_set_calculator->getType(), $this->wrapped_calculator->getType()));
		}
		$this->wrapped_calculator = $exception_set_calculator;
	}

	public function getWrappedCalculators() {
		$wrapped = [];
		if ($this->wrapped_calculator !== null) {
			$wrapped[] = $this->wrapped_calculator;
		}
		return $wrapped;
	}

	public function determineForScope(Scope $scope) {
		$this->traverser->traverse(array($scope));
	}

	public function getForScope(Scope $scope) {
		return $this->wrapped_calculator->getForScope($scope);
	}

	public function scopeHasChanged(Scope $scope, $reset = true) {
		return $this->wrapped_calculator->scopeHasChanged($scope, $reset);
	}

	public function getType() {
		return sprintf("traversing %s", $this->wrapped_calculator->getType());
	}

	/**
	 * @return Scope[]
	 */
	public function getScopesChangedDuringLastTraverse() {
		return $this->visitor->getChangedDuringLastTraverse();
	}

}