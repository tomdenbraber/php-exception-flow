<?php
namespace PhpExceptionFlow\AstVisitor;

use PhpExceptionFlow\GuardedScope;
use PhpExceptionFlow\Scope;
use PhpParser\Node;
use PhpParser\NodeVisitorAbstract;
use PHPTypes\State;

/**
 * Class ScopeCollector
 * This class Visits the AST and collects all Scopes and GuardedScopes it encounters.
 * @package PhpExceptionFlow\Visitor
 */
class ScopeCollector extends NodeVisitorAbstract {
	/** @var Scope $main_scope */
	private $main_scope;
	/** @var Scope[] $function_scopes */
	private $function_scopes;
	/** @var Scope $current_scope */
	private $current_scope;
	/** @var GuardedScope current_guarded_scope */
	private $current_guarded_scope;

	/** @var State $state */
	private $state;

	public function __construct(State $state) {
		$this->main_scope = new Scope("{main}");
		$this->function_scopes = array();
		$this->current_scope = $this->main_scope;
		$this->state = $state;

		$this->current_guarded_scope = null;
	}

	public function enterNode(Node $node) {
		if ($node instanceof Node\Stmt\Function_) {
			/** @var Node\Stmt\Function_ $node */
			$this->current_scope = new Scope($node->name);
		} else if ($node instanceof Node\Stmt\TryCatch) {
			$enclosing_scope = $this->current_scope;
			$inclosed_scope = new Scope(md5(random_bytes(64)));
			$new_guarded_scope = new GuardedScope($enclosing_scope, $inclosed_scope);
			$enclosing_scope->addGuardedScope($new_guarded_scope);
			$inclosed_scope->setEnclosingGuardedScope($new_guarded_scope);

			$this->current_scope = $inclosed_scope;
			$this->current_guarded_scope = $new_guarded_scope;
		} else if ($node instanceof Node\Stmt\Catch_) {
			$this->current_guarded_scope->addCatchClause($node);
		} else if ($node instanceof Node\Stmt) {
			$this->current_scope->addInstruction($node);
		}
	}

	public function leaveNode(Node $node) {
		if ($node instanceof Node\Stmt\Function_) {
			// go back to main scope when we leave a function
			$this->function_scopes[] = $this->current_scope;
			$this->current_scope = $this->main_scope;
		} else if ($node instanceof Node\Stmt\TryCatch) {
			// calculate the types of the catch clauses
			$this->current_guarded_scope->determineCaughtExceptionTypes($this->state);

			// restore the current scope to the scope in which this try/catch block resides
			// restore the current guarded scope to the the guarded scope in which the current scope resides
			$this->current_scope = $this->current_guarded_scope->getEnclosingScope();
			if ($this->current_scope->isEnclosed() === true) { //restore also the guardedscope
				$this->current_guarded_scope = $this->current_scope->getEnclosingGuardedScope();
			} else {
				$this->current_guarded_scope = null;
			}
		}
	}

	/**
	 * @return Scope
	 */
	public function getMainScope() {
		return $this->main_scope;
	}

	/**
	 * @return Scope[]
	 */
	public function getFunctionScopes() {
		return $this->function_scopes;
	}
}