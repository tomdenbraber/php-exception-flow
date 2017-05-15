<?php
namespace PhpExceptionFlow\Collection\Test;

//this class is only used for testing the PartialOrder class
use PhpExceptionFlow\Collection\PartialOrder\PartialOrderElementInterface;

class Number implements PartialOrderElementInterface {
	public $value;

	public function __construct($value) {
		$this->value = $value;
	}

	public function __toString() {
		return (string)$this->value;
	}
}