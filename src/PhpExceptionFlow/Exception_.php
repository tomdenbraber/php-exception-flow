<?php
namespace PhpExceptionFlow;

use PhpExceptionFlow\Path\PathEntryInterface;
use PhpExceptionFlow\Path\Propagates;
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
	/** @var Path[] */
	private $propagation_paths = [];
	/** @var GuardedScope[] */
	private $escaped_guarded_scopes = [];

	public function __construct(Type $type, Node\Stmt $initial_cause, Scope $caused_in) {
		$this->type = $type;
		$this->initial_cause = $initial_cause;
		$this->propagation_paths[] = Path::fromInitialScope($caused_in);
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
		return $this->propagation_paths;
	}

	/**
	 * @return Node|Node\Stmt
	 */
	public function getInitialCause() {
		return $this->initial_cause;
	}

	/**
	 * @return GuardedScope[]
	 */
	public function getEscapedGuardedScopes() {
		return $this->escaped_guarded_scopes;
	}

	/**
	 * @param Scope $called_scope
	 * @param Scope $caller_scope
	 */
	public function propagate(Scope $called_scope, Scope $caller_scope) {
		$this->addPropagationLink(new Propagates($caller_scope), $called_scope);
	}

	public function uncaught(GuardedScope $escaped_scope, Scope $enclosing_scope) {
		$this->addPropagationLink(new Uncaught($enclosing_scope, $escaped_scope), $escaped_scope->getInclosedScope());

	}

	private function addPropagationLink(PathEntryInterface $path_entry, Scope $last_scope) {
		foreach ($this->propagation_paths as $propagation_path) {
			if ($propagation_path->getLastEntryInChain()->getScope() === $last_scope) {
				$new_path = $propagation_path->addEntry($path_entry);
				if ($this->pathAlreadyExists($new_path) === false) {
					$this->propagation_paths[] = $new_path;
				}
			}
		}
	}


	private function pathAlreadyExists(Path $path) {
		foreach ($this->propagation_paths as $existing_path) {
			if ($path->equals($existing_path) === true) {
				return true;
			}
		}
		return false;
	}

	public function __toString() {
		return (string) $this->getType();
	}
}