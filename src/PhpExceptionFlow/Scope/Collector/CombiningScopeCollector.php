<?php

namespace PhpExceptionFlow\Scope\Collector;

use PhpExceptionFlow\Scope\Scope;

class CombiningScopeCollector implements CallableScopeCollector {

	/** @var CallableScopeCollector[] */
	private $collectors = [];

	/**
	 * CombiningScopeCollector constructor.
	 * @param CallableScopeCollector[] $collectors
	 */
	public function __construct(array $collectors = []) {
		foreach ($collectors as $collector) {
			$this->addCollector($collector);
		}
	}

	/**
	 * @param CallableScopeCollector $collector
	 */
	public function addCollector(CallableScopeCollector $collector) {
		$this->collectors[] = $collector;
	}

	/**
	 * @return Scope[]
	 */
	public function getAllScopes() {
		return $this->combineOutput("getAllScopes");

	}

	/**
	 * @return Scope[]
	 */
	public function getTopLevelScopes() {
		return $this->combineOutput("getTopLevelScopes");
	}

	/**
	 * @return Scope[]
	 */
	public function getNonFunctionScopes() {
		return $this->combineOutput("getNonFunctionScopes");
	}


	/**
	 * @param boolean $flat
	 * @return Scope[]|Scope[][]
	 */
	public function getMethodScopes($flat = false) {
		return $this->combineOutputWithArg("getMethodScopes", $flat);
	}

	/**
	 * @param boolean $flat
	 * @return Scope[]
	 */
	public function getFunctionScopes($flat = false) {
		return $this->combineOutputWithArg("getFunctionScopes", $flat);
	}

	/**
	 * @return null|Scope
	 */
	public function getMainScope() {
		foreach ($this->collectors as $collector) {
			$main_scope = $collector->getMainScope();
			if ($main_scope !== null) {
				return $main_scope;
			}
		}
		return null;
	}

	/**
	 * @param string $function_name
	 * @param bool $arg
	 * @return Scope[]|Scope[][]
	 */
	private function combineOutputWithArg($function_name, $arg = false) {
		$scopes = [];
		foreach ($this->collectors as $collector) {
			$scopes = array_merge($scopes, $collector->$function_name($arg));
		}
		return $scopes;
	}

	/**
	 * @param string $function_name
	 * @return Scope[]
	 */
	private function combineOutput($function_name) {
		$scopes = [];
		foreach ($this->collectors as $collector) {
			$scopes = array_merge($scopes, $collector->$function_name());
		}
		return $scopes;
	}
}