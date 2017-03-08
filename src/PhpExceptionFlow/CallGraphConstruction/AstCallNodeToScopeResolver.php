<?php
namespace PhpExceptionFlow\CallGraphConstruction;

use PhpExceptionFlow\Scope\Scope;
use PhpParser\Node;
use PHPTypes\Type;

class AstCallNodeToScopeResolver implements CallResolverInterface {
	/** @var Scope[][] $method_scopes */
	private $method_scopes;
	/** @var Scope[] $function_scopes */
	private $function_scopes;
	/** @var Method[][][] $class_method_to_implementations */
	private $class_method_to_implementations;

	/**
	 * @param Scope[][] $method_scopes
	 * @param Scope[] $function_scopes
	 * @param array $class_method_to_method
	 */
	public function __construct(array $method_scopes, array $function_scopes, array $class_method_to_method) {
		$this->method_scopes = $method_scopes;
		$this->function_scopes = $function_scopes;
		$this->class_method_to_implementations = $class_method_to_method;
	}

	/**
	 * @param Node $func_call
	 * @return Scope[]
	 * @throws \UnexpectedValueException
	 * @throws \LogicException
	 */
	public function resolve(Node $func_call) {
		switch (get_class($func_call)) {
			case Node\Expr\FuncCall::class:
				/** @var Node\Expr\FuncCall $func_call */
				return [$this->resolveFuncCall($func_call)];
			case Node\Expr\MethodCall::class:
				/** @var Node\Expr\MethodCall $func_call */
				return $this->resolveMethodCall($func_call);
			case Node\Expr\StaticCall::class:
				/** @var Node\Expr\StaticCall $func_call */
				return $this->resolveStaticCall($func_call);
				break;
			case Node\Expr\New_::class:
				/** @var Node\Expr\New_ $func_call */
				return $this->resolveConstructorCall($func_call);
			default:
				throw new \LogicException("This type of node cannot be handled: " . get_class($func_call));
		}
	}

	/**
	 * @param Node\Expr\FuncCall $call
	 * @throws \UnexpectedValueException
	 * @return Scope
	 */
	private function resolveFuncCall(Node\Expr\FuncCall $call) {
		if ($call->name instanceof Node\Name) {
			$func_name = implode("\\", $call->name->parts);
			if (isset($this->function_scopes[$func_name]) === true) {
				return $this->function_scopes[$func_name];
			} else {
				throw new \UnexpectedValueException(sprintf("Function %s() could not be found!", $func_name));
			}
		} else {
			throw new \UnexpectedValueException(sprintf("Cannot resolve function call; function expression has type %s", $call->name->getAttribute("type", Type::unknown())));
		}
	}

	/**
	 * @param Node\Expr\MethodCall $call
 	 * @throws \UnexpectedValueException
	 * @return Scope[]
	 */
	private function resolveMethodCall(Node\Expr\MethodCall $call) {
		/** @var Type $type */
		$type = $call->var->getAttribute("type", Type::unknown());
		if ($type->type === Type::TYPE_OBJECT) {
			$class = strtolower($type->userType);
			if (is_string($call->name) === true && isset($this->class_method_to_implementations[$class][$call->name]) === true) {
				$method_implementations = $this->class_method_to_implementations[$class][$call->name];
				$called_scopes = [];
				foreach ($method_implementations as $method_implementation) {
					$called_method_name = strtolower($method_implementation->getName());
					$called_method_class = strtolower($method_implementation->getClass());
					if (isset($this->method_scopes[$called_method_class][$called_method_name]) === true) {
						$called_scopes[] = $this->method_scopes[$called_method_class][$called_method_name];
					} else {
						throw new \UnexpectedValueException(sprintf("Method %s->%s() could not be found in method scopes", $class, $call->name));
					}
				}
				return $called_scopes;
			} else {
				throw new \UnexpectedValueException(sprintf("Method %s->%s() could not be found in applies to set (%d) ", $class, is_string($call->name) === true ? $call->name : $call->name->getType(), $call->getLine()));
			}
		} else {
			throw new \UnexpectedValueException(sprintf("Cannot resolve method call; var %s has type %s, method-name is %s (%d)", $call->var->getType(), $type, is_string($call->name) === true ? $call->name : $call->name->getType(), $call->getLine()));
		}
	}

	/**
	 * @param Node\Expr\StaticCall $call
	 * @throws \UnexpectedValueException
	 * @return Scope[]
	 */
	private function resolveStaticCall(Node\Expr\StaticCall $call) {
		if ($call->class instanceof Node\Name) {
			$class = strtolower(implode("\\", $call->class->parts));
			if (is_string($call->name) === true && isset($this->class_method_to_implementations[$class][$call->name]) === true) {
				/** @var Method[] $called_methods */
				$called_methods = $this->class_method_to_implementations[$class][$call->name];
				$called_scopes = [];
				foreach ($called_methods as $called_method) {
					$called_method_name = strtolower($called_method->getName());
					$called_method_class = strtolower($called_method->getClass());
					if (isset($this->method_scopes[$called_method_class][$called_method_name]) === true) {
						$called_scopes[] = $this->method_scopes[$called_method_class][$called_method_name];
					} else {
						throw new \UnexpectedValueException(sprintf("Method %s::%s() could not be found in method scopes", $class, $call->name));
					}
				}
				return $called_scopes;
			} else {
				throw new \UnexpectedValueException(sprintf("Method %s::%s() could not be found in applies to set (%d) ", $class, is_string($call->name) === true ? $call->name : $call->name->getType(), $call->getLine()));
			}
		} else {
			throw new \UnexpectedValueException(sprintf("Cannot resolve static call; class expression has type %s, method-name is %s", $call->class->getAttribute("type", Type::unknown()), $call->name));
		}
	}

	private function resolveConstructorCall(Node\Expr\New_ $call) {
		if ($call->class instanceof Node\Name) {
			$class = strtolower(implode("\\", $call->class->parts));
			$constructor_name = '__construct';
			if (isset($this->class_method_to_implementations[$class]) === true && isset($this->class_method_to_implementations[$class][$constructor_name]) === true) {
				/** @var Method[] $called_methods */
				$called_methods = $this->class_method_to_implementations[$class][$constructor_name];
				$called_scopes = [];
				foreach ($called_methods as $called_method) {
					$called_method_name = strtolower($called_method->getName());
					$called_method_class = strtolower($called_method->getClass());
					if (isset($this->method_scopes[$called_method_class][$called_method_name]) === true) {
						$called_scopes[] = $this->method_scopes[$called_method_class][$called_method_name];
					} else {
						print sprintf("Method %s::__construct() could not be found in method scopes\n", $class);
						throw new \UnexpectedValueException(sprintf("Method %s::__construct() could not be found in method scopes", $class));
					}
				}
				return $called_scopes;
			} else {
				print sprintf("Method %s::__construct() could not be found in applies to set (%d)\n", $class, $call->getLine());
				throw new \UnexpectedValueException(sprintf("Method %s::__construct() could not be found in applies to set (%d)", $class, $call->getLine()));
			}
		} else {
			print sprintf("Cannot resolve constructor call; class expression has type %s\n", $call->class->getAttribute("type", Type::unknown()));
			throw new \UnexpectedValueException(sprintf("Cannot resolve constructor call; class expression has type %s", $call->class->getAttribute("type", Type::unknown())));
		}
	}
}