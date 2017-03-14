<?php
namespace PhpExceptionFlow\Path;

use PhpExceptionFlow\Scope\GuardedScope;
use PhpExceptionFlow\Scope\Scope;

class Uncaught extends AbstractPathEntry {
	/** @var GuardedScope $escaped_scope */
	private $escaped_scope;

	public function __construct(Scope $scope, GuardedScope $escaped_scope) {
		parent::__construct($scope);
		$this->escaped_scope = $escaped_scope;
	}

	public function getType() {
		return "uncaught";
	}

	/**
	 * @return GuardedScope
	 */
	public function getEscapedScope() {
		return $this->escaped_scope;
	}

	public function equals(PathEntryInterface $path_entry) {
		return parent::equals($path_entry) &&
			$path_entry instanceof Uncaught &&
			$this->escaped_scope = $path_entry->getEscapedScope();
	}
}