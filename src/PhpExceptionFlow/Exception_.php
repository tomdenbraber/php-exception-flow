<?php
namespace PhpExceptionFlow;

use PhpExceptionFlow\Path\PathCollection;
use PhpExceptionFlow\Path\PathCollectionInterface;
use PhpExceptionFlow\Path\PathEntryInterface;
use PhpExceptionFlow\Path\Propagates;
use PhpExceptionFlow\Path\Raises;
use PhpExceptionFlow\Path\Uncaught;
use PhpExceptionFlow\Scope\GuardedScope;
use PhpExceptionFlow\Scope\Scope;
use PhpParser\Node;
use PHPTypes\Type;
use PhpExceptionFlow\Path\Path;

class Exception_ {
	/** @var Type */
	private $type;
	/** @var Node\Stmt */
	private $initial_cause;
	/** @var Path $path */
	private $path;

	public function __construct(Type $type, Node\Stmt $initial_cause, Scope $caused_in) {
		$this->type = $type;
		$this->initial_cause = $initial_cause;
		$this->path_collection = new PathCollection();
		//$this->path_collection->addPath(Path::fromInitialScope($caused_in));
		$this->path = Path::fromInitialScope($caused_in);
	}

	/**
	 * @return Type
	 */
	public function getType() {
		return $this->type;
	}

	/**
	 * @return Path[]
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

	public function propagate(Scope $called_scope, Scope $caller_scope) {
		$entry = new Propagates($called_scope, $caller_scope);
		$this->path->addEntry($entry);
	}

	public function uncaught(GuardedScope $escaped_scope, Scope $enclosing_scope) {
		$entry = new Uncaught($escaped_scope, $enclosing_scope);
		$this->path->addEntry($entry);
	}

	/**
	 * @param Scope $called_scope
	 * @param Scope $caller_scope
	 */
	/*public function propagate(Scope $called_scope, Scope $caller_scope) {
		$this->addPropagationLink(new Propagates($caller_scope), $called_scope);
	}

	public function uncaught(GuardedScope $escaped_scope, Scope $enclosing_scope) {
		$this->addPropagationLink(new Uncaught($enclosing_scope, $escaped_scope), $escaped_scope->getInclosedScope());
	}

	private function addPropagationLink(PathEntryInterface $path_entry, Scope $last_scope) {
		$possible_last_entries = [
			new Propagates($last_scope),
			new Raises($last_scope),
		];

		if ($last_scope->isEnclosed() === true) {
			$possible_last_entries[] = new Uncaught($last_scope, $last_scope->getEnclosingGuardedScope());
		}

		foreach ($possible_last_entries as $possible_last_entry) {
			foreach ($this->path_collection->getPathsEndingIn($possible_last_entry) as $path) {
				$new_path = $path->addEntry($path_entry);
				if ($this->path_collection->containsPath($new_path) === false) {
					$this->path_collection->addPath($new_path);
				}
			}
		}
	}*/

	public function __toString() {
		return (string) $this->getType();
	}
}