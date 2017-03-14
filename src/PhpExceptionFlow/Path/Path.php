<?php

namespace PhpExceptionFlow\Path;

use PhpExceptionFlow\Scope\Scope;

class Path {
	/** @var PathEntryInterface[] */
	private $chain;
	/** @var int $path_length */
	private $path_length = 0;

	/**
	 * PropagationPath constructor.
	 * @param PathEntryInterface[] $scope_chain
	 * @param int $count
	 */
	private function __construct(array $scope_chain, int $count) {
		$this->chain = $scope_chain;
		$this->path_length = $count;

		if ($count > 100) {
			$first = $this->chain[0];
			$last = $this->chain[$this->path_length - 1];
			print sprintf("path starting in %s(%s), ending in %s(%s) has %d entries\n", $first->getType(), $first->getScope()->getName(), $last->getType(), $last->getScope()->getName(), $this->path_length);
		}
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

		return new Path($new_chain, $this->path_length + 1);
	}

	/**
	 * @return PathEntryInterface
	 */
	public function getLastEntryInChain() {
		return $this->chain[$this->path_length - 1];
	}

	/**
	 * @param Path $path
	 * @return bool
	 */
	public function equals(Path $path) {
		if ($this->getLength() === $path->getLength()) {
			$other_scope_chain = $path->getChain();
			for ($i = $this->getLength() - 1; $i >= 0; $i--) {
				if ($other_scope_chain[$i]->equals($this->chain[$i]) === false) {
					return false;
				}
			}
			return true;
		}
		return false;
	}

	public function getLength() {
		return $this->path_length;
	}

	/**
	 * @param Scope $scope
	 * @return Path
	 */
	public static function fromInitialScope(Scope $scope) {
		$scope_chain = [new Raises($scope)];
		return new Path($scope_chain, 1);
	}
}