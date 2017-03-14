<?php
namespace PhpExceptionFlow\Path;

interface PathCollectionInterface {
	/**
	 * @param Path $path
	 * @return bool
	 */
	public function containsPath(Path $path);

	/**
	 * @param Path $path
	 * @return void
	 */
	public function addPath(Path $path);

	/**
	 * @param PathEntryInterface $entry
	 * @return Path[]
	 */
	public function getPathsEndingIn(PathEntryInterface $entry);

	/**
	 * @return Path[]
	 */
	public function getPaths();
}