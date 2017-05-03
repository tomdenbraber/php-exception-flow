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
	public function getFromScope();

	/**
	 * @return Scope|null
	 */
	public function getToScope();

	/**
	 * @param PathEntryInterface $path_entry
	 * @return bool
	 */
	public function equals(PathEntryInterface $path_entry);

	/**
	 * @return string
	 */
	public function __toString();
}