<?php
namespace PhpExceptionFlow\Path;

class Propagates extends AbstractPathEntry {
	public function getType() {
		return "propagates";
	}
}