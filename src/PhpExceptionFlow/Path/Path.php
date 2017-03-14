<?php

namespace PhpExceptionFlow\Path;

use PhpExceptionFlow\Scope\Scope;

class Path {

	/** @var PathEntryInterface $initial_link */
	private $initial_link;

	private $entries = [];

	/** @var \SplObjectStorage $scope_links */
	private $scope_links;

	public function __construct(PathEntryInterface $initial_link) {
		$this->initial_link = $initial_link;
		$this->scope_links = new \SplObjectStorage;
	}


	/**
	 * @return PathEntryInterface[]
	 */
	public function getPaths() {


		return [];
	}

	/**
	 * @param PathEntryInterface $entry
	 */
	public function addEntry(PathEntryInterface $entry) {
		$this->entries[(string)$entry] = $entry;

		$available_links = $this->scope_links[$entry->getFromScope()] ?? [];
		$available_links[] = $entry->getToScope();
		$this->scope_links[$entry->getFromScope()] = $available_links;
	}


	public function pathContainsEntry(PathEntryInterface $entry) {
		$entry_key = (string)$entry;
		return isset($this->entries[$entry_key]) === true;
	}

	/**
	 * @return PathEntryInterface
	 */
	public function getLastEntryInChain() {
		return $this->chain[$this->path_length - 1];
	}

	/**
	 * @param Path $path
	 * @return bool
	 */
	public function equals(Path $path) {
		if ($this->getLength() === $path->getLength()) {
			$other_scope_chain = $path->getChain();
			for ($i = $this->getLength() - 1; $i >= 0; $i--) {
				if ($other_scope_chain[$i]->equals($this->chain[$i]) === false) {
					return false;
				}
			}
			return true;
		}
		return false;
	}

	public function getLength() {
		return $this->path_length;
	}
}