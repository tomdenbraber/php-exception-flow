<?php
namespace PhpExceptionFlow;

class ScopeTraverser implements ScopeTraverserInterface {

	/** @var ScopeVisitorInterface[]  */
	private $visitors = [];

	/**
	 * Traverser constructor.
	 * @param ScopeVisitorInterface[] $visitors
	 */
	public function __construct(array $visitors = array()) {
		foreach ($visitors as $visitor) {
			$this->addVisitor($visitor);
		}
	}

	/**
	 * @param $visitor ScopeVisitorInterface
	 * @return void
	 */
	public function addVisitor(ScopeVisitorInterface $visitor) {
		$this->visitors[] = $visitor;
	}

	/**
	 * @param Scope[] $scopes
	 * @return void
	 */
	public function traverse(array $scopes) {
		foreach ($this->visitors as $visitor) {
			$visitor->beforeTraverse($scopes);
		}

		foreach ($scopes as $scope) {
			$this->traverseScope($scope);
		}

		foreach ($this->visitors as $visitor) {
			$visitor->afterTraverse($scopes);
		}
	}

	/**
	 * @param Scope $scope
	 */
	private function traverseScope(Scope $scope) {
		foreach ($this->visitors as $visitor) {
			$visitor->enterScope($scope);
		}

		$this->traverseGuardedScopeArray($scope->getGuardedScopes());

		foreach ($this->visitors as $visitor) {
			$visitor->leaveScope($scope);
		}
	}

	/**
	 * @param GuardedScope[] $guarded_scopes
	 */
	private function traverseGuardedScopeArray(array $guarded_scopes) {
		foreach ($guarded_scopes as $guarded_scope) {
			foreach ($this->visitors as $visitor) {
				$visitor->enterGuardedScope($guarded_scope);
			}

			$this->traverseGuardedScope($guarded_scope);

			foreach ($this->visitors as $visitor) {
				$visitor->leaveGuardedScope($guarded_scope);
			}
		}
	}

	/**
	 * @param GuardedScope $guarded_scope
	 */
	private function traverseGuardedScope(GuardedScope $guarded_scope) {
		$this->traverseScope($guarded_scope->getInclosedScope());
	}
}