<?php
namespace PhpExceptionFlow\AstVisitor;

use PhpExceptionFlow\Scope\Collector\CallableScopeCollector;
use PhpExceptionFlow\Scope\GuardedScope;
use PhpExceptionFlow\Scope\Scope;
use PhpParser\Node;
use PhpParser\NodeVisitorAbstract;

/**
 * Class ScopeCollector
 * This class Visits the AST and collects all Scopes and GuardedScopes it encounters.
 * @package PhpExceptionFlow\Visitor
 */
class ScopeCollector extends NodeVisitorAbstract implements CallableScopeCollector {
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

	/** @var Node[] */
	private $current_scope_node_stack = [];
	/** @var Node[][] */
	private $node_stacks = [];

	/** @var int indicates how many try/catch nodes still reside in the AST while they should be removed */
	private $try_catches_to_be_removed = 0;

	public function __construct() {
		$this->main_scope = new Scope("{main}");
		$this->current_scope = $this->main_scope;

		$this->current_guarded_scope = null;
		$this->current_class = null;
	}

	public function enterNode(Node $node) {
		if ($node instanceof Node\Stmt\Namespace_) {
			$this->current_namespace = strtolower(implode("\\", $node->name->parts));
		} else if ($node instanceof Node\Stmt\ClassLike) {
			$this->current_class = $node;
		} else if ($node instanceof Node\FunctionLike) {
			switch (get_class($node)) {
				case Node\Expr\Closure::class:
					//todo: implement correctly, thinking of how closures behave.
					//currently do nothing to not interfere with 'normal' function scopes
					break;
				case Node\Stmt\Function_::class:
					/** @var Node\Stmt\Function_ $node */
					$name = $node->name;
					$this->current_scope = new Scope($name);
					break;
				case Node\Stmt\ClassMethod::class:
					/** @var Node\Stmt\ClassMethod $node */
					$name = $this->current_class->name . "::" . $node->name;
					$this->current_scope = new Scope($name);
					break;
				default:
					throw new \LogicException("Unknown implementation of FunctionLike: " . get_class($node));
			}
		} else if ($node instanceof Node\Stmt\TryCatch) {
			$enclosing = $this->current_scope;
			$inclosed = new Scope(sprintf("%s try #%d", $enclosing->getName(), count($enclosing->getGuardedScopes()) + 1));
			$guarded_scope = new GuardedScope($enclosing, $inclosed);
			$inclosed->setEnclosingGuardedScope($guarded_scope);
			$enclosing->addGuardedScope($guarded_scope);
			// switch node stacks
			$this->node_stacks[] = $this->current_scope_node_stack;
			$this->current_scope_node_stack = [];

			$this->current_scope = $inclosed;
			$this->current_guarded_scope = $guarded_scope;
		} else if ($node instanceof Node\Stmt\Catch_) {
			$this->current_guarded_scope->addCatchClause($node);
		} else {
			$this->current_scope_node_stack[] = $node;
		}
	}

	public function leaveNode(Node $node) {
		if ($node instanceof Node\Stmt\Namespace_) {
			$this->current_namespace = "";
		} else if ($node instanceof Node\Stmt\ClassLike) {
			$this->current_class = null;
		} else if ($node instanceof Node\FunctionLike) {
			// go back to main scope when we leave a function
			if ($node instanceof Node\Stmt\Function_) {
				$this->function_scopes[strtolower($node->name)] = $this->current_scope;
				$this->current_scope = $this->main_scope;
			} else if ($node instanceof Node\Stmt\ClassMethod) {
				$cls_name = strlen($this->current_namespace) > 0 ? $this->current_namespace . "\\" . strtolower($this->current_class->name) : strtolower($this->current_class->name);
				$this->method_scopes[$cls_name][strtolower($node->name)] = $this->current_scope;
				$this->current_scope = $this->main_scope;
			} else if ($node instanceof Node\Expr\Closure) {
				//todo: implement correct closure handling
			}
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
			//restore the node stack of the enclosing scope
			$this->current_scope_node_stack = array_pop($this->node_stacks);
			$this->try_catches_to_be_removed += 1;
		} else if ($node instanceof Node\Stmt\Catch_) {
			//skip it, it is also added via GuardedScope->addCatchClause
		} else {
			$popped_node = array_pop($this->current_scope_node_stack);
			if ($popped_node !== $node) {
				throw new \LogicException(sprintf("Node popped from stack does not correspond with node that is left: %s (left) vs %s (popped).", $node->getType(), $popped_node->getType()));
			}
			if (count($this->current_scope_node_stack) === 0) { //this node is directly below the current scope level, so add it to the current scope
				$this->current_scope->addInstruction($node);
			}

			/**
			 * guarded scopes should not have their instructions inserted into normal scopes.
			 * Therefore, remove the try/catch (and consequently, all its children) from the current node
			 */
			if ($this->try_catches_to_be_removed > 0) {
				$before = $this->try_catches_to_be_removed;
				foreach ($node->getSubNodeNames() as $sub_node_name) {
					if (is_array($node->$sub_node_name) === true) {
						foreach ($node->$sub_node_name as $i => $stmt) {
							if ($stmt instanceof Node\Stmt\TryCatch === true) {
								unset($node->$sub_node_name[$i]);
								$this->try_catches_to_be_removed -= 1;
							}
						}
					}
				}
				if ($before !== $this->try_catches_to_be_removed) {
					return $node; //indicates that we have changed the node; if we do not change anything, there is no need to return it.
				}
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
}