<?php

namespace PhpExceptionFlow\Scope\ScopeVisitor;

use PhpExceptionFlow\Scope\GuardedScope;
use PhpExceptionFlow\Scope\Scope;

abstract class AbstractScopeVisitor implements ScopeVisitorInterface {

	public function beforeTraverse(array $scopes) {
	}

	public function afterTraverse(array $scopes) {
	}

	public function enterScope(Scope $scope) {
	}

	public function leaveScope(Scope $scope) {
	}

	public function enterGuardedScope(GuardedScope $guarded_scope) {
	}

	public function leaveGuardedScope(GuardedScope $guarded_scope) {
	}
}
