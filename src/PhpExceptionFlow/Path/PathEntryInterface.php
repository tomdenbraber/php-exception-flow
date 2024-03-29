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
	 * @return Scope
	 */
	public function getToScope();

	/**
	 * @param PathEntryInterface $path_entry
	 * @return bool
	 */
	public function equals(PathEntryInterface $path_entry);

	/**
	 * Some path entries are inherently always the last entry in a chain, as further propagation is impossible (for Catch clauses)
	 * @return bool
	 */
	public function isLastEntry();

	/**
	 * @return string
	 */
	public function __toString();
}