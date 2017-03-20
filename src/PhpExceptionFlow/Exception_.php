<?php
namespace PhpExceptionFlow;

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
	 * @return bool[]
	 */
	public function getCauses(Scope $scope) {
		$entries = $this->path_collection->getEntriesForToScope($scope);
		$res = [
			"raises" => false,
			"propagates" => false,
			"uncaught" => false,
		];
		foreach ($entries as $entry) {
			$res[$entry->getType()] = true;
		}
		return $res;
	}

	public function __toString() {
		return (string) $this->getType();
	}
}