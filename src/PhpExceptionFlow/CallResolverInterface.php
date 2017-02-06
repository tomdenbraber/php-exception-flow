<?php
namespace PhpExceptionFlow;

use PhpParser\Node;

interface CallResolverInterface {
	/**
	 * @param Node $func_call
	 * @return null|Scope
	 * @throws \UnexpectedValueException
	 * @throws \LogicException
	 */
	public function resolve(Node $func_call);
}