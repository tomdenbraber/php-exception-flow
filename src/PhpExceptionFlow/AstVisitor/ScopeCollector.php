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
	/** @var Scope[] $non_function_scopes */
	private $non_function_scopes;

	/** @var Scope $current_scope */
	private $current_scope;
	/** @var GuardedScope current_guarded_scope */
	private $current_guarded_scope;


	/** @var Node\Stmt\ClassLike */
	private $current_class;

	/** @var State $state */
	private $state;

	public function __construct(State $state) {
		$this->main_scope = new Scope("{main}");
		$this->function_scopes = array();
		$this->current_scope = $this->main_scope;
		$this->state = $state;

		$this->current_guarded_scope = null;
		$this->current_class = null;
	}

	public function enterNode(Node $node) {
		if ($node instanceof Node\Stmt\ClassLike) {
			$this->current_class = $node;
		} else if ($node instanceof Node\FunctionLike) {
			$name = "";
			switch (get_class($node)) {
				case Node\Expr\Closure::class:
					//todo: implement correctly, thinking of how closures behave.
					$name = md5(random_bytes(64));
					break;
				case Node\Stmt\Function_::class:
					/** @var Node\Stmt\Function_ $node */
					$name = $node->name;
					break;
				case Node\Stmt\ClassMethod::class:
					/** @var Node\Stmt\ClassMethod $node */
					$name = $this->current_class->name . "::" . $node->name;
					break;
				default:
					throw new \LogicException("Unknown implementation of FunctionLike: " . get_class($node));
			}

			$this->current_scope = new Scope($name);
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
		if ($node instanceof Node\Stmt\ClassLike) {
			$this->current_class = null;
		} else if ($node instanceof Node\FunctionLike) {
			// go back to main scope when we leave a function
			$this->function_scopes[] = $this->current_scope;
			$this->current_scope = $this->main_scope;
		} else if ($node instanceof Node\Stmt\TryCatch) {
			//a scope inside a try catch can never be a function scope, so add to non-function scopes
			$this->non_function_scopes[] = $this->current_scope;
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

	/**
	 * @return Scope[]
	 */
	public function getNonFunctionScopes() {
		return $this->non_function_scopes;
	}

	/**
	 * @return Scope[]
	 */
	public function getAllScopes() {
		return array_merge(array($this->main_scope), $this->function_scopes, $this->non_function_scopes);
	}
}