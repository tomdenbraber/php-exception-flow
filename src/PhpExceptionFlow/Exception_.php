<?php
namespace PhpExceptionFlow;

use PhpExceptionFlow\Path\Catches;
use PhpExceptionFlow\Path\PathCollection;
use PhpExceptionFlow\Path\PathEntryInterface;
use PhpExceptionFlow\Path\Propagates;
use PhpExceptionFlow\Path\Raises;
use PhpExceptionFlow\Path\Uncaught;
use PhpExceptionFlow\Scope\GuardedScope;
use PhpExceptionFlow\Scope\Scope;
use PhpParser\Node;
use PHPTypes\Type;

class Exception_ {
	/** @var Type */
	private $type;
	/** @var Node\Stmt */
	private $initial_cause;
	/** @var PathCollection $path_collection */
	private $path_collection;

	public function __construct(Type $type, Node\Stmt $initial_cause, Scope $caused_in) {
		$this->type = $type;
		$this->initial_cause = $initial_cause;
		$this->path_collection = new PathCollection(new Raises($caused_in));
	}

	/**
	 * @return Type
	 */
	public function getType() {
		return $this->type;
	}

	/**
	 * @return PathEntryInterface[][]
	 */
	public function getPropagationPaths() {
		return $this->path_collection->getPaths();
	}

	/**
	 * @return Node|Node\Stmt
	 */
	public function getInitialCause() {
		return $this->initial_cause;
	}

	/**
	 * @param Scope $called_scope
	 * @param Scope $caller_scope
	 */
	public function propagate(Scope $called_scope, Scope $caller_scope) {
		$entry = new Propagates($called_scope, $caller_scope);
		if ($this->path_collection->containsEntry($entry) === false) {
			$this->path_collection->addEntry($entry);
		}
	}

	/**
	 * @param GuardedScope $escaped_scope
	 * @param Scope $enclosing_scope
	 */
	public function uncaught(GuardedScope $escaped_scope, Scope $enclosing_scope) {
		$entry = new Uncaught($escaped_scope, $enclosing_scope);
		if ($this->path_collection->containsEntry($entry) === false) {
			$this->path_collection->addEntry($entry);
		}
	}

	/**
	 * @param GuardedScope $caught_in
	 * @param Node\Stmt\Catch_ $caught_by
	 */
	public function catches(GuardedScope $caught_in, Node\Stmt\Catch_ $caught_by) {
		$entry = new Catches($caught_in, $caught_by);
		if ($this->path_collection->containsEntry($entry) === false) {
			$this->path_collection->addEntry($entry);
		}
	}

	/**
	 * @return Scope[][]
	 */
	public function getCauses(Scope $scope) {
		$entries = $this->path_collection->getEntriesForToScope($scope);
		$res = [
			"raises" => [],
			"propagates" => [],
			"uncaught" => [],
		];
		foreach ($entries as $entry) {
			$res[$entry->getType()][] = $entry->getFromScope();
		}
		return $res;
	}

	/**
	 * @param Scope $scope
	 * @return false|PathEntryInterface false if the path does not end in the given scope, else the corresponding entry
	 */
	public function pathEndsIn(Scope $scope) {
		$entries = $this->path_collection->getEntriesForFromScope($scope);
		foreach ($entries as $entry) {
			if ($entry->isLastEntry() === true) {
				return $entry;
			}
		}
		return false;
	}

	/**
	 * Gets all the paths that end in a catch-clause
	 * @return array
	 */
	public function getPathsToCatchClause(Node\Stmt\Catch_ $catch_) {
		$links = $this->path_collection->getEntries();
		$paths = [];
		foreach ($links as $link) {
			if ($link instanceof Catches && $link->getCaughtBy() === $catch_) {
				foreach ($this->path_collection->getPathsEndingInLink($link) as $path) {
					$paths[] = $path;
				}
			}
		}
		return $paths;
	}

	public function __toString() {
		return (string) $this->getType();
	}
}