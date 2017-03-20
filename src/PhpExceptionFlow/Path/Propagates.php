<?php
namespace PhpExceptionFlow\Path;

use PhpExceptionFlow\Scope\Scope;

class Propagates extends AbstractPathEntry {
	/** @var Scope $from_scope */
	private $from_scope;
	/** @var Scope $to_scope */
	private $to_scope;

	public function __construct(Scope $from_scope, Scope $to_scope) {
		$this->from_scope = $from_scope;
		$this->to_scope = $to_scope;
	}

	public function getFromScope() {
		return $this->from_scope;
	}

	public function getToScope() {
		return $this->to_scope;
	}


	public function getType() {
		return "propagates";
	}
}