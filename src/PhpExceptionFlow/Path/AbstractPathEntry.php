<?php
namespace PhpExceptionFlow\Path;

use PhpExceptionFlow\Scope\Scope;

abstract class AbstractPathEntry implements PathEntryInterface {
	/** @var Scope $scope  */
	private $scope;

	public function __construct(Scope $scope) {
		$this->scope = $scope;
	}

	public function getScope() {
		return $this->scope;
	}

	public function equals(PathEntryInterface $path_entry) {
		return $path_entry->getType() === $this->getType() &&
			$path_entry->getScope() === $this->getScope();
	}

	public function __toString() {
		return $this->getType() . ":" . $this->scope->getName();
	}
}