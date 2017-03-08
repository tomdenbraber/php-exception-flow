<?php
namespace PhpExceptionFlow\Scope\Collector;

use PhpExceptionFlow\Scope\Scope;
use PHPTypes\InternalArgInfo;

/**
 * The Builtin collector collects all functions/classes that are available in PHP and creates scopes for them.
 */
class BuiltInCollector implements CallableScopeCollector {
	/** @var InternalArgInfo $internal_arg_info */
	private $internal_arg_info;

	private $function_scopes = [];
	private $method_scopes = [];

	public function __construct(InternalArgInfo $internal_arg_info) {
		$this->internal_arg_info = $internal_arg_info;
		$this->calculateFunctionScopes();
		$this->calculateMethodScopes();
	}

	/**
	 * @return null
	 */
	public function getMainScope() {
		return null;
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
		return [];
	}

	public function getTopLevelScopes() {
		return array_merge($this->getFunctionScopes(true), $this->getMethodScopes(true));
	}


	/**
	 * @return Scope[]
	 */
	public function getAllScopes() {
		return $this->getTopLevelScopes();
	}

	private function calculateFunctionScopes() {
		foreach ($this->internal_arg_info->functions as $function => $_) {
			$this->function_scopes[$function] = new Scope($function);
		}
	}

	private function calculateMethodScopes() {
		foreach ($this->internal_arg_info->methods as $class => $_) {
			if (isset($this->method_scopes[$class]) === false) {
				$this->method_scopes[$class] = [];
			}
			foreach ($this->internal_arg_info->methods[$class] as $method => $__) {
				$this->method_scopes[$class][$method] = new Scope($class . "::" . $method);
			}
		}
	}
}