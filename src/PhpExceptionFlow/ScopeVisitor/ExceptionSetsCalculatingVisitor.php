<?php

namespace PhpExceptionFlow\ScopeVisitor;

use PhpExceptionFlow\GuardedScope;
use PhpExceptionFlow\Scope;
use PHPTypes\State;

class ExceptionSetsCalculatingVisitor extends AbstractScopeVisitor {
	/** @var State $state */
	private $state;

	public function __construct(State $state) {
		$this->state = $state;
	}

	public function leaveScope(Scope $scope) {
		$scope->determineEncounters();
	}

	public function leaveGuardedScope(GuardedScope $guarded_scope) {
		$guarded_scope->determineUncaughtExceptions($this->state, true);
	}
}