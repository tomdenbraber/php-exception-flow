<?php

namespace PhpExceptionFlow\Scope\Collector;

use PhpExceptionFlow\Scope\Scope;

interface CallableScopeCollector {
	/**
	 * @return Scope|null
	 */
	public function getMainScope();

	/**
	 * @param boolean $flat
	 * @return Scope[]
	 */
	public function getFunctionScopes($flat = false);

	/**
	 * @param boolean $flat
	 * @return Scope[][]|Scope[]
	 */
	public function getMethodScopes($flat = false);
	/**
	 * @return Scope[]
	 */
	public function getNonFunctionScopes();

	/**
	 * @return Scope[]
	 */
	public function getTopLevelScopes();


	/**
	 * @return Scope[]
	 */
	public function getAllScopes();
}