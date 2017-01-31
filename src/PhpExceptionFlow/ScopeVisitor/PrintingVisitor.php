<?php
namespace PhpExceptionFlow\ScopeVisitor;

use PhpExceptionFlow\FlowCalculator\MutableCombiningCalculator;
use PhpExceptionFlow\FlowCalculator\UncaughtCalculator;
use PhpExceptionFlow\GuardedScope;
use PhpExceptionFlow\Scope;

class PrintingVisitor extends AbstractScopeVisitor {
	private $indent = "";
	private $current_top_level_scope = "";
	private $result = "";

	/** @var  MutableCombiningCalculator $encounters_calculator */
	private $encounters_calculator;
	/** @var UncaughtCalculator $uncaught_calculator */
	private $uncaught_calculator;


	public function __construct(MutableCombiningCalculator $encounters_calculator) {
		$this->encounters_calculator = $encounters_calculator;
		$this->uncaught_calculator = $encounters_calculator->getCalculator("uncaught");
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
			$this->result .= $this->indent . "catch#" . $i . " catches: [" . implode(", ",$this->uncaught_calculator->getCaughtExceptions($catch_clause)) . "]\n";
		}
		$this->result .= $this->indent . "uncaught: [" . implode(", ", $this->uncaught_calculator->getForGuardedScope($guarded_scope)) . "]\n";
	}

	public function getResult() {
		return $this->result;
	}
}