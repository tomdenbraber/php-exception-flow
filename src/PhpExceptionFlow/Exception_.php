<?php
namespace PhpExceptionFlow;

use PhpExceptionFlow\Scope\GuardedScope;
use PhpExceptionFlow\Scope\Scope;
use PhpParser\Node;
use PHPTypes\Type;

class Exception_ {
	/** @var Type */
	private $type;
	/** @var Node\Stmt */
	private $initial_cause;
	/** @var PropagationPath[] */
	private $propagation_paths = [];
	/** @var GuardedScope[] */
	private $escaped_guarded_scopes = [];

	public function __construct(Type $type, Node\Stmt $initial_cause, Scope $caused_in) {
		$this->type = $type;
		$this->initial_cause = $initial_cause;
		$this->propagation_paths[] = PropagationPath::fromInitialScope($caused_in);
	}

	/**
	 * @return Type
	 */
	public function getType() {
		return $this->type;
	}

	/**
	 * @return PropagationPath[]
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
		foreach ($this->propagation_paths as $propagation_path) {
			if ($propagation_path->getLastScopeInChain() === $called_scope) {
				$new_path = $propagation_path->addCall($called_scope, $caller_scope);
				if ($this->pathAlreadyExists($new_path) === false) {
					$this->propagation_paths[] = $new_path;
				}
			}
		}
	}

	public function pathAlreadyExists(PropagationPath $path) {
		foreach ($this->propagation_paths as $propagation_path) {
			if ($path->getScopeChain() === $propagation_path->getScopeChain()) {
				return true;
			}
		}
		return false;
	}

	public function __toString() {
		return (string) $this->getType();
	}
}