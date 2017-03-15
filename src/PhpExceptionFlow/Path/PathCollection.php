<?php

namespace PhpExceptionFlow\Path;

class PathCollection {

	/** @var PathEntryInterface $initial_link */
	private $initial_link;

	private $entries = [];

	/** @var \SplObjectStorage|PathEntryInterface[][] $scope_links */
	private $scope_links;

	public function __construct(PathEntryInterface $initial_link) {
		$this->initial_link = $initial_link;
		$this->scope_links = new \SplObjectStorage;
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

		$covered_links = [];

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


			$next_links = $this->scope_links[$link->getToScope()] ?? [];

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

		$available_links = $this->scope_links[$entry->getFromScope()] ?? [];
		$available_links[] = $entry;
		$this->scope_links[$entry->getFromScope()] = $available_links;
	}


	public function containsEntry(PathEntryInterface $entry) {
		$entry_key = (string)$entry;
		return isset($this->entries[$entry_key]) === true;
	}
}