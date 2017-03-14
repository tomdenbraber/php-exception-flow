<?php
namespace PhpExceptionFlow\Path;

abstract class AbstractPathEntry implements PathEntryInterface {

	public function equals(PathEntryInterface $path_entry) {
		return $path_entry->getType() === $this->getType() &&
			$path_entry->getFromScope() === $this->getFromScope() &&
			$path_entry->getToScope() === $this->getToScope();
	}

	public function __toString() {
		return $this->getType() . ":" . $this->getFromScope()->getName() . "->" . $this->getToScope()->getName();
	}
}