<?php
namespace PhpExceptionFlow\Path;

use PhpExceptionFlow\Scope\Scope;

interface PathEntryInterface {
	/**
	 * @return string
	 */
	public function getType();

	/**
	 * @return Scope
	 */
	public function getScope();

	/**
	 * @param PathEntryInterface $path_entry
	 * @return bool
	 */
	public function equals(PathEntryInterface $path_entry);
}