<?php
namespace PhpExceptionFlow\ScopeVisitor;

use PhpExceptionFlow\GuardedScope;
use PhpExceptionFlow\Scope;

class PrintingVisitor extends AbstractScopeVisitor {
	private $indent = "";
	private $current_top_level_scope = "";
	private $result = "";

	public function beforeTraverse(array $scopes) {
		$this->result = "";
	}

	public function enterScope(Scope $scope) {
		if ($scope->getEnclosingGuardedScope() === null) {
			$this->current_top_level_scope = $scope->getName();
			$this->result .= $this->indent . $this->current_top_level_scope . ":\n";
		}

		$this->result .= $this->indent . "\t" . "encounters: [" . implode(", ",$scope->getEncounters()) . "]\n";
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
			$this->result .= $this->indent . "catch#" . $i . " catches: [" . implode(", ",$guarded_scope->getCaughtExceptionsForClause($catch_clause)) . "]\n";
		}
		$this->result .= $this->indent . "uncaught: [" . implode(", ",$guarded_scope->getUncaught()) . "]\n";
	}

	public function getResult() {
		return $this->result;
	}
}