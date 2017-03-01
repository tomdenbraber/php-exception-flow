<?php

namespace PhpExceptionFlow;

use PhpExceptionFlow\Scope\Scope;

class PropagationPath {
	/** @var Scope[] */
	private $scope_chain;
	/**
	 * @var \SplObjectStorage $scope_to_index
	 * contains the highest index for each scope in the call chain
	 */
	private $scope_to_index;

	/**
	 * PropagationPath constructor.
	 * @param Scope[] $scope_chain
	 * @param \SplObjectStorage $scope_to_index
	 */
	private function __construct(array $scope_chain, \SplObjectStorage $scope_to_index) {
		$this->scope_chain = $scope_chain;
		$this->scope_to_index = $scope_to_index;
	}

	/**
	 * @return Scope[]
	 */
	public function getScopeChain() {
		return $this->scope_chain;
	}

	/**
	 * For lack of a better name.
	 * @param Scope $caller
	 * @param Scope $callee
	 * @return bool
	 */
	public function lastOcccurrencesOfScopesAreCallingEachother(Scope $caller, Scope $callee) {
		return $this->scope_to_index->contains($caller) &&
			$this->scope_to_index->contains($callee) &&
			$this->scope_to_index[$callee] === $this->scope_to_index[$caller] + 1;
	}

	/**
	 * @param Scope $from
	 * @param Scope $to
	 * @return PropagationPath
	 */
	public function addCall(Scope $from, Scope $to) {
		$caller_index = $this->scope_to_index[$from];
		$new_scope_chain = array_slice($this->scope_chain, 0, $caller_index + 1);
		$new_scope_to_index = new \SplObjectStorage;

		foreach ($new_scope_chain as $scope) {
			$new_scope_to_index->attach($scope, $this->scope_to_index[$scope]);
		}

		$new_scope_chain[] = $to;
		$new_scope_to_index[$to] = $caller_index + 1;

		return new PropagationPath($new_scope_chain, $new_scope_to_index);
	}

	/**
	 * @param Scope $scope
	 * @return PropagationPath
	 */
	public static function fromInitialScope(Scope $scope) {
		$scope_chain = [$scope];
		$scope_to_index = new \SplObjectStorage;
		$scope_to_index->attach($scope, 0);
		return new PropagationPath($scope_chain, $scope_to_index);
	}
}