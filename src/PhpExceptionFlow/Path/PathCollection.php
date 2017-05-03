<?php

namespace PhpExceptionFlow\Path;

use PhpExceptionFlow\Scope\Scope;

class PathCollection {

	/** @var PathEntryInterface $initial_link */
	private $initial_link;

	private $entries = [];

	/** @var \SplObjectStorage|PathEntryInterface[][] $scope_from_links */
	private $scope_from_links;
	/** @var \SplObjectStorage|PathEntryInterface[][] $scope_to_links */
	private $scope_to_links;

	public function __construct(PathEntryInterface $initial_link) {
		$this->initial_link = $initial_link;
		$this->scope_from_links = new \SplObjectStorage;
		$this->scope_to_links = new \SplObjectStorage;

		$this->addEntry($initial_link);
	}


	/**
	 * @return PathEntryInterface[][]
	 */
	public function getPaths() {
		$paths = [
			[$this->initial_link]
		];
		$link_to_ind = [(string)$this->initial_link => [0]];
		$no_paths = 1;

		$covered_links = [(string)$this->initial_link => true];

		/** @var PathEntryInterface[] $queue */
		$queue = [$this->initial_link];
		while (empty($queue) === false) {
			$link = array_shift($queue);

			$paths_ending_in_current_elem = [];
			$indices = $link_to_ind[(string)$link] ?? [];
			foreach ($indices as $index) {
				$paths_ending_in_current_elem[] = $paths[$index];
			}
			if (empty($paths_ending_in_current_elem) === true) {
				$paths_ending_in_current_elem[] = [];
			}

			if ($link->isLastEntry() === false) {
				$next_links = $this->scope_from_links[$link->getToScope()] ?? [];
			} else {
				$next_links = [];
			}

			foreach ($next_links as $next_link) {
				if (isset($covered_links[(string)$next_link]) === false) {
					foreach ($paths_ending_in_current_elem as $i => $relevant_path) {
						$relevant_path[]  = $next_link;
						$paths[] = $relevant_path;
						if (isset($link_to_ind[(string)$next_link]) === true) {
							$link_to_ind[(string)$next_link][] = $no_paths;
						} else {
							$link_to_ind[(string)$next_link] = [$no_paths];
						}
						$no_paths += 1;
					}
				}
			}

			$covered_links[(string)$link] = true;

			foreach ($next_links as $next_link) {
				if (isset($covered_links[(string)$next_link]) === false) {
					$queue[] = $next_link;
				}
			}
		}
		return $paths;
	}

	/**
	 * @param PathEntryInterface $entry
	 */
	public function addEntry(PathEntryInterface $entry) {
		$this->entries[(string)$entry] = $entry;

		$available__to_links = $this->scope_from_links[$entry->getFromScope()] ?? [];
		$available__to_links[] = $entry;
		$this->scope_from_links[$entry->getFromScope()] = $available__to_links;

		if ($entry->isLastEntry() === false) {
			$available_from_links = $this->scope_to_links[$entry->getToScope()] ?? [];
			$available_from_links[] = $entry;
			$this->scope_to_links[$entry->getToScope()] = $available_from_links;
		}
	}


	public function containsEntry(PathEntryInterface $entry) {
		$entry_key = (string)$entry;
		return isset($this->entries[$entry_key]) === true;
	}

	public function getEntriesForToScope(Scope $scope) {
		if ($this->scope_to_links->contains($scope) === true) {
			return $this->scope_to_links[$scope];
		} else {
			return [];
		}
	}
}