<?php
namespace PhpExceptionFlow\Path;

use PhpExceptionFlow\Scope\Scope;

class Raises extends AbstractPathEntry {
	/** @var Scope $raised_in_scope */
	private $raised_in_scope;

	public function __construct(Scope $raised_in_scope) {
		$this->raised_in_scope = $raised_in_scope;
	}

	public function getFromScope() {
		return $this->raised_in_scope;
	}

	public function getToScope() {
		return $this->raised_in_scope;
	}

	public function getType() {
		return "raises";
	}
}