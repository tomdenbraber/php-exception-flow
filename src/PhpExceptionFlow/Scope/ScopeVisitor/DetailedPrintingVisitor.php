<?php
namespace PhpExceptionFlow\Scope\ScopeVisitor;

use PhpExceptionFlow\FlowCalculator\CombiningCalculator;
use PhpExceptionFlow\FlowCalculator\CombiningCalculatorInterface;
use PhpExceptionFlow\FlowCalculator\PropagatesCalculator;
use PhpExceptionFlow\FlowCalculator\RaisesCalculator;
use PhpExceptionFlow\FlowCalculator\UncaughtCalculator;
use PhpExceptionFlow\Scope\GuardedScope;
use PhpExceptionFlow\Scope\Scope;

class DetailedPrintingVisitor extends AbstractScopeVisitor {
	/** @var RaisesCalculator */
	private $raises_calculator;
	/** @var UncaughtCalculator */
	private $uncaught_calculator;
	/** @var PropagatesCalculator */
	private $propagates_calculator;
	/** @var CombiningCalculatorInterface */
	private $encounters_calculator;

	/** @var Scope */
	private $current_top_level_scope;

	private $result;
	private $indent;

	public function __construct(RaisesCalculator $raises, UncaughtCalculator $uncaught, PropagatesCalculator $propagates) {
		$this->raises_calculator = $raises;
		$this->uncaught_calculator = $uncaught;
		$this->propagates_calculator = $propagates;

		$this->encounters_calculator = new CombiningCalculator();
		$this->encounters_calculator->addCalculator($this->raises_calculator);
		$this->encounters_calculator->addCalculator($this->uncaught_calculator);
		$this->encounters_calculator->addCalculator($this->propagates_calculator);
	}

	public function beforeTraverse(array $scopes) {
		$this->result = "";
	}

	public function enterScope(Scope $scope) {
		if ($scope->getEnclosingGuardedScope() === null) {
			$this->current_top_level_scope = $scope->getName();
			$this->result .= $this->indent . $this->current_top_level_scope . ":\n";
		}

		$encounters = $this->encounters_calculator->getForScope($scope);
		$raises = array_unique($this->raises_calculator->getForScope($scope));
		$propagates = array_unique($this->propagates_calculator->getForScope($scope));
		$uncaught = array_unique($this->uncaught_calculator->getForScope($scope));
		$this->result .= $this->indent . "\t" . "encounters: [" . implode(", ", $encounters) . "]\n";
		$this->result .= $this->indent . "\t" . "raises: [" . implode(", ", $raises) . "]\n";
		$this->result .= $this->indent . "\t" . "propagates: [" . implode(", ", $propagates) . "]\n";
		$this->result .= $this->indent . "\t" . "uncaught: [" . implode(", ", $uncaught) . "]\n";
		$this->indent .= "\t";
	}

	public function leaveScope(Scope $scope) {
		$this->indent = substr($this->indent, 1);
	}

	public function enterGuardedScope(GuardedScope $guarded_scope) {
		$this->result .= $this->indent . "try\n";
	}

	public function leaveGuardedScope(GuardedScope $guarded_scope) {
		foreach ($guarded_scope->getCatchClauses() as $i => $catch_clause) {
			$this->result .= sprintf("%scatch#%d catches: [%s], could catch: [%s]\n",
				$this->indent,
				$i,
				implode(", ", $this->uncaught_calculator->getCaughtExceptions($catch_clause)),
				implode(", ", $this->uncaught_calculator->getPotentiallyCaughtTypes($catch_clause))
			);
		}
		$this->result .= $this->indent . "uncaught: [" . implode(", ", $this->uncaught_calculator->getForGuardedScope($guarded_scope)) . "]\n";
	}

	public function getResult() {
		return $this->result;
	}
}