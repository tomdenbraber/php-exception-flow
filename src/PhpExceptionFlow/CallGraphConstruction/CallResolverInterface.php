<?php
namespace PhpExceptionFlow\CallGraphConstruction;

use PhpExceptionFlow\Scope\Scope;
use PhpParser\Node;

interface CallResolverInterface {
	/**
	 * @param Node $func_call
	 * @return Scope[]
	 * @throws \UnexpectedValueException
	 * @throws \LogicException
	 */
	public function resolve(Node $func_call);
}