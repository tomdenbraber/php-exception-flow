<?php
namespace PhpExceptionFlow;

interface ScopeTraverserInterface
{
	/**
	 * @param ScopeVisitorInterface $visitor
	 */
	public function addVisitor(ScopeVisitorInterface $visitor);

	/**
	 * @param Scope[] $scopes
	 * @return mixed
	 */
	public function traverse(array $scopes);

}