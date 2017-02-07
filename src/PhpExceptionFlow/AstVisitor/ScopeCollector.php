<?php
namespace PhpExceptionFlow\AstVisitor;

use PhpExceptionFlow\Scope\GuardedScope;
use PhpExceptionFlow\Scope\Scope;
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
	/** @var Scope[] $function_scopes, indexed by name */
	private $function_scopes = [];
	/** @var Scope[][] $method_scopes, indexed by class, method name */
	private $method_scopes = [];
	/** @var Scope[] $non_function_scopes */
	private $non_function_scopes = [];

	/** @var Scope $current_scope */
	private $current_scope;
	/** @var GuardedScope current_guarded_scope */
	private $current_guarded_scope;

	private $current_namespace = "";

	/** @var Node\Stmt\ClassLike */
	private $current_class;

	/** @var State $state */
	private $state;

	public function __construct(State $state) {
		$this->main_scope = new Scope("{main}");
		$this->current_scope = $this->main_scope;
		$this->state = $state;

		$this->current_guarded_scope = null;
		$this->current_class = null;
	}

	public function beforeTraverse(array $nodes) {
		//add all top level nodes that are not declarations/try-catches to the main scope
		$this->addInstructionsToScope($this->main_scope, $nodes);
	}

	public function enterNode(Node $node) {
		if ($node instanceof Node\Stmt\Namespace_) {
			$this->current_namespace = strtolower(implode("\\", $node->name->parts));
		} else if ($node instanceof Node\Stmt\ClassLike) {
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
			if (is_array($node->getStmts()) === true) { //when method is abstract/defined in interface, it getStmts may return null
				$this->addInstructionsToScope($this->current_scope, $node->getStmts());
			}
		} else if ($node instanceof Node\Stmt\TryCatch) {
			$enclosing_scope = $this->current_scope;
			$inclosed_scope = new Scope(md5(random_bytes(64)));
			$new_guarded_scope = new GuardedScope($enclosing_scope, $inclosed_scope);
			$enclosing_scope->addGuardedScope($new_guarded_scope);
			$inclosed_scope->setEnclosingGuardedScope($new_guarded_scope);
			$this->addInstructionsToScope($inclosed_scope, $node->stmts);

			$this->current_scope = $inclosed_scope;
			$this->current_guarded_scope = $new_guarded_scope;
		} else if ($node instanceof Node\Stmt\Catch_) {
			$this->current_guarded_scope->addCatchClause($node);
		}
	}

	public function leaveNode(Node $node) {
		if ($node instanceof Node\Stmt\ClassLike) {
			$this->current_class = null;
		} else if ($node instanceof Node\FunctionLike) {
			// go back to main scope when we leave a function
			if ($node instanceof Node\Stmt\Function_) {
				$this->function_scopes[strtolower($node->name)] = $this->current_scope;
			} else if ($node instanceof Node\Stmt\ClassMethod) {
				$cls_name = strlen($this->current_namespace) > 0 ? $this->current_namespace . "\\" . strtolower($this->current_class->name) : strtolower($this->current_class->name);
				$this->method_scopes[$cls_name][strtolower($node->name)] = $this->current_scope;
			}
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
	 * @param boolean $flat
	 * @return Scope[]
	 */
	public function getFunctionScopes($flat = false) {
		if ($flat === true) {
			return array_values($this->function_scopes);
		}
		return $this->function_scopes;
	}

	/**
	 * @param boolean $flat
	 * @return Scope[][]|Scope[]
	 */
	public function getMethodScopes($flat = false) {
		if ($flat === true) {
			$method_scopes = [];
			foreach ($this->method_scopes as $class => $methods) {
				$method_scopes = array_merge($method_scopes, array_values($methods));
			}
			return $method_scopes;
		}
		return $this->method_scopes;
	}

	/**
	 * @return Scope[]
	 */
	public function getNonFunctionScopes() {
		return $this->non_function_scopes;
	}

	public function getTopLevelScopes() {
		return array_merge(array($this->main_scope), $this->getFunctionScopes(true), $this->getMethodScopes(true));
	}


	/**
	 * @return Scope[]
	 */
	public function getAllScopes() {
		return array_merge(array($this->main_scope), $this->getFunctionScopes(true), $this->getMethodScopes(true), $this->non_function_scopes);
	}

	/**
	 * @param Scope $scope
	 * @param array $nodes
	 */
	private function addInstructionsToScope(Scope $scope, array $nodes) {
		foreach ($nodes as $stmt) {
			if (($stmt instanceof Node\Stmt\ClassLike) === false &&
				($stmt instanceof Node\FunctionLike) === false &&
				($stmt instanceof Node\Stmt\TryCatch) === false) {
				$scope->addInstruction($stmt);
			}
		}
	}
}