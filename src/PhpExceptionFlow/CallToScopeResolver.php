<?php
namespace PhpExceptionFlow;

use PhpExceptionFlow\CHA\Method;
use PhpParser\Node;
use PHPTypes\Type;

class CallToScopeResolver {
	/** @var array $method_scopes */
	private $method_scopes;
	/** @var array $function_scopes */
	private $function_scopes;
	/** @var array $applies_to */
	private $applies_to;

	/**
	 * @param Scope[][] $method_scopes
	 * @param Scope[] $function_scopes
	 * @param array $applies_to
	 */
	public function __construct(array $method_scopes, array $function_scopes, array $applies_to) {
		$this->method_scopes = $method_scopes;
		$this->function_scopes = $function_scopes;
		$this->applies_to = $applies_to;
	}

	/**
	 * @param Node $func_call
	 * @return null|Scope
	 * @throws \LogicException
	 */
	public function resolve(Node $func_call) {
		switch (get_class($func_call)) {
			case Node\Expr\FuncCall::class:
				/** @var Node\Expr\FuncCall $func_call */
				return $this->resolveFuncCall($func_call);
			case Node\Expr\MethodCall::class:
				/** @var Node\Expr\MethodCall $func_call */
				return $this->resolveMethodCall($func_call);
			case Node\Expr\StaticCall::class:
				/** @var Node\Expr\StaticCall $func_call */
				return $this->resolveStaticCall($func_call);
				break;
			default:
				throw new \LogicException("This type of node cannot be handled: " . get_class($func_call));
		}
	}

	/**
	 * @param Node\Expr\FuncCall $call
	 * @throws \LogicException
	 * @return Scope|null
	 */
	private function resolveFuncCall(Node\Expr\FuncCall $call) {
		if ($call->name instanceof Node\Name) {
			$func_name = implode("\\", $call->name->parts);
			if (isset($this->function_scopes[$func_name]) === true) {
				return $this->function_scopes[$func_name];
			} else {
				throw new \LogicException(sprintf("Function %s() could not be found!", $func_name));
			}
		}
		return null;
	}

	/**
	 * @param Node\Expr\MethodCall $call
	 * @throws \LogicException
	 * @return Scope|null
	 */
	private function resolveMethodCall(Node\Expr\MethodCall $call) {
		/** @var Type $type */
		$type = $call->var->getAttribute("type", Type::unknown());
		if ($type->type === Type::TYPE_OBJECT) {
			$class = strtolower($type->userType);
			if (is_string($call->name) === true && isset($this->applies_to[$class][$call->name]) === true) {
				/** @var Method $called_method */
				$called_method = $this->applies_to[$class][$call->name];
				$called_method_name = $called_method->getName();
				$called_method_class = strtolower($called_method->getClass());
				if (isset($this->method_scopes[$called_method_class][$called_method_name]) === true) {
					return $this->method_scopes[$called_method_class][$called_method_name];
				} else {
					throw new \LogicException(sprintf("Method %s->%s() could not be found in method scopes", $class, $call->name));
				}
			} else {
				throw new \LogicException(sprintf("Method %s->%s() could not be found in applies to set", $class, $call->name));
			}
		}
		return null;
	}

	/**
	 * @param Node\Expr\StaticCall $call
	 * @throws \LogicException
 	 * @return Scope|null
	 */
	private function resolveStaticCall(Node\Expr\StaticCall $call) {
		if ($call->class instanceof Node\Name) {
			$class = implode("\\", $call->class->parts);
			if (is_string($call->name) === true && isset($this->applies_to[$class][$call->name]) === true) {
				/** @var Method $called_method */
				$called_method = $this->applies_to[$class][$call->name];
				$called_method_name = $called_method->getName();
				$called_method_class = strtolower($called_method->getClass());
				if (isset($this->method_scopes[$called_method_class][$called_method_name]) === true) {
					return $this->method_scopes[$called_method_class][$called_method_name];
				} else {
					throw new \LogicException(sprintf("Method %s::%s() could not be found in method scopes", $class, $call->name));
				}
			} else {
				throw new \LogicException(sprintf("Method %s::%s() could not be found in applies to set", $class, $call->name));
			}
		}
		return null;
	}
}