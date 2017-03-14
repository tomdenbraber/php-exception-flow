<?php
namespace PhpExceptionFlow\Path;

class PathCollection implements PathCollectionInterface {

	/** @var int[][] */
	private $last_entry_to_ind = [];

	/** @var Path[] */
	private $paths = [];

	/** @var int */
	private $no_paths = 0;

	public function addPath(Path $path) {
		$last_entry = $path->getLastEntryInChain();
		$last_entry_key = (string)$last_entry;
		$indices = $this->last_entry_to_ind[$last_entry_key] ?? [];

		$indices[] = $this->no_paths;
		$this->last_entry_to_ind[$last_entry_key] = $indices;

		$this->paths[] = $path;
		$this->no_paths += 1;
	}

	public function containsPath(Path $path) {
		$last_entry = $path->getLastEntryInChain();
		$last_entry_key = (string)$last_entry;
		if (isset($this->last_entry_to_ind[$last_entry_key]) === true) {
			foreach ($this->last_entry_to_ind[$last_entry_key] as $index) {
				if ($this->paths[$index]->equals($path) === true) {
					return true;
				}
			}
		}
		return false;
	}

	public function getPathsEndingIn(PathEntryInterface $entry) {
		$indices = $this->last_entry_to_ind[(string)$entry] ?? [];
		$paths = [];
		foreach ($indices as $index) {
			$paths[] = $this->paths[$index];
		}
		print "paths ending in " . $entry . ": " . count($paths) . "\n";
		return $paths;
	}

	public function getPaths() {
		return $this->paths;
	}

	public function getNumberOfPaths() {
		return $this->no_paths;
	}

}