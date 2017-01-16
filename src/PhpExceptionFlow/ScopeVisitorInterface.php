<?php
namespace PhpExceptionFlow;

interface ScopeVisitorInterface {

	public function beforeTraverse(array $scopes);

	public function afterTraverse(array $scopes);

	public function enterScope(Scope $scope);

	public function leaveScope(Scope $scope);

	public function enterGuardedScope(GuardedScope $guarded_scope);

	public function leaveGuardedScope(GuardedScope $guarded_scope);

}