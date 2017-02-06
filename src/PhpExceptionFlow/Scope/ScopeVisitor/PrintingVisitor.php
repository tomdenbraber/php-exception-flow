<?php
namespace PhpExceptionFlow\Scope\ScopeVisitor;

use PhpExceptionFlow\FlowCalculator\CombiningCalculatorInterface;
use PhpExceptionFlow\FlowCalculator\UncaughtCalculator;
use PhpExceptionFlow\Scope\GuardedScope;
use PhpExceptionFlow\Scope\Scope;

class PrintingVisitor extends AbstractScopeVisitor {
	private $indent = "";
	private $current_top_level_scope = "";
	private $result = "";

	/** @var  CombiningCalculatorInterface $encounters_calculator */
	private $encounters_calculator;
	/** @var UncaughtCalculator $uncaught_calculator */
	private $uncaught_calculator;


	public function __construct(CombiningCalculatorInterface $encounters_calculator, UncaughtCalculator $uncaught_calculator) {
		$this->encounters_calculator = $encounters_calculator;
		$this->uncaught_calculator = $uncaught_calculator;
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
		print $scope->getName() . " - [" . implode(", ", $encounters) . "]\n";
		$this->result .= $this->indent . "\t" . "encounters: [" . implode(", ", $encounters) . "]\n";
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