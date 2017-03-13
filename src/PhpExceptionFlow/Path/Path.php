<?php

namespace PhpExceptionFlow\Path;

use PhpExceptionFlow\Scope\Scope;

class Path {
	/** @var PathEntryInterface[] */
	private $chain;

	/**
	 * PropagationPath constructor.
	 * @param PathEntryInterface[] $scope_chain
	 */
	private function __construct(array $scope_chain) {
		$this->chain = $scope_chain;
	}

	/**
	 * @return PathEntryInterface[]
	 */
	public function getChain() {
		return $this->chain;
	}

	/**
	 * @param PathEntryInterface $next_entry
	 * @return Path
	 */
	public function addEntry(PathEntryInterface $next_entry) {
		$new_chain = $this->chain;
		$new_chain[] = $next_entry;

		return new Path($new_chain);
	}

	/**
	 * @return PathEntryInterface
	 */
	public function getLastEntryInChain() {
		return $this->chain[count($this->chain) - 1];
	}

	/**
	 * @param Path $path
	 * @return bool
	 */
	public function equals(Path $path) {
		$other_scope_chain = $path->getChain();
		if (count($this->chain) === count($other_scope_chain)) {
			print count($this->chain) . "===" .  count($other_scope_chain). "\n";
			foreach ($other_scope_chain as $i => $other_path_entry) {
				if ($other_path_entry->equals($this->chain[$i]) === false) {
					return false;
				}
			}
			return true;
		}
		return false;
	}


	/**
	 * @param Scope $scope
	 * @return Path
	 */
	public static function fromInitialScope(Scope $scope) {
		$scope_chain = [new Raises($scope)];
		return new Path($scope_chain);
	}
}