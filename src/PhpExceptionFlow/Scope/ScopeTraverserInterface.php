<?php
namespace PhpExceptionFlow\Scope;

use PhpExceptionFlow\Scope\ScopeVisitor\ScopeVisitorInterface;

interface ScopeTraverserInterface
{
	/**
	 * @param ScopeVisitorInterface $visitor
	 */
	public function addVisitor(ScopeVisitorInterface $visitor);

	/**
	 * @param ScopeVisitorInterface $visitor
	 */
	public function removeVisitor(ScopeVisitorInterface $visitor);

	/**
	 * @param Scope[] $scopes
	 * @return mixed
	 */
	public function traverse(array $scopes);

}