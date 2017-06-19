<?php

namespace PhpExceptionFlow\Path;

use PhpExceptionFlow\Scope\Scope;

class PathCollection {

	/** @var PathEntryInterface $initial_link */
	private $initial_link;

	/** @var PathEntryInterface[]  */
	private $entries = [];

	/** @var \SplObjectStorage|PathEntryInterface[][] $scope_from_links */
	private $scope_from_links;
	/** @var \SplObjectStorage|PathEntryInterface[][] $scope_to_links */
	private $scope_to_links;

	public function __construct(PathEntryInterface $initial_link) {
		$this->initial_link = $initial_link;
		$this->scope_from_links = new \SplObjectStorage;
		$this->scope_to_links = new \SplObjectStorage;

		$this->entries[] = $initial_link;
		$this->scope_to_links[$initial_link->getToScope()] = [$initial_link];
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
						$relevant_path[] = $next_link;
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

	public function getEntriesForFromScope(Scope $scope) {
		if ($this->scope_from_links->contains($scope) === true) {
			return $this->scope_from_links[$scope];
		} else {
			return [];
		}
	}

	public function getEntries() {
		return $this->entries;
	}

	/**
	 * Does a DFS creation of all possible paths (leaves out cycles)
	 * @param PathEntryInterface $final_link
	 * @return \Generator
	 */
	public function getPathsEndingInLink(PathEntryInterface $final_link) {
		$stack = [$final_link];
		$covered_entries = [];
		$currently_stacked_entries = [
			(string) $final_link => true
		];
		$covered_for_current_root = [];

		while (empty($stack) === false) {
			/** @var PathEntryInterface $current_entry */
			$current_entry = $stack[0];
			if (isset($covered_for_current_root[(string)$current_entry]) === false) {
				$covered_for_current_root[(string)$current_entry] = [];
			}

			$links = $this->scope_to_links[$current_entry->getFromScope()];
			$added_link_to_stack = false;
			foreach ($links as $link) {
				if (isset($covered_entries[(string)$link]) === false && isset($currently_stacked_entries[(string)$link]) === false && isset($covered_for_current_root[(string)$current_entry][(string)$link]) === false) {
					array_unshift($stack, $link);
					$added_link_to_stack = true;
					$currently_stacked_entries[(string)$link] = true;
					$covered_for_current_root[(string)$current_entry][(string)$link] = true;
					break;
				}
			}

			if ($added_link_to_stack === false) {
				$top_item = $stack[0];
				//this path is finished. if it ends in the initial link, it is a path from $initial_link to $final_link.
				if ($top_item === $this->initial_link) {
					yield $stack;
				}
				$finished_entry = array_shift($stack);
				unset($currently_stacked_entries[(string)$top_item]);
				$covered_entries[(string)$top_item] = true;
				$this->cleanCoveredEntries((string)$finished_entry, $covered_entries, $covered_for_current_root);
			}
		}
	}

	/**
	 * Uses Dijkstra to find the shortest path from the initial link to the given final link
	 * @param PathEntryInterface $final_link
	 * @return PathEntryInterface[]
	 */
	public function getShortestPathEndingInLink(PathEntryInterface $final_link) : array {
		$queue = [];

		$distances = new \SplObjectStorage();
		$previous = new \SplObjectStorage();

		$distances[$this->initial_link] = 0;

		foreach ($this->entries as $node) {
			if ($node !== $this->initial_link) {
				$distances[$node] = INF;
				$previous[$node] = null;
			}
			$queue[] = $node;
		}

		while (empty($queue) === false) {
			/** @var PathEntryInterface $current_entry */
			$min_dist = INF;
			$min_index = -1;
			//fetch entry with lowest distance from the queue
			foreach ($queue as $i => $entry) {
				if ($distances[$entry] < $min_dist) {
					$min_dist = $distances[$entry];
					$current_entry = $entry;
					$min_index = $i;
				}
			}
			array_splice($queue, $min_index, 1);


			if ($current_entry === $final_link) {
				break;
			}


			if ($this->scope_from_links->contains($current_entry->getToScope()) === true) {
				foreach ($this->scope_from_links[$current_entry->getToScope()] as $neighbour) {
					$dist = $distances[$current_entry] + 1; //the distance between each scope is the same, so +1
					if ($dist < $distances[$neighbour]) {
						$distances[$neighbour] = $dist;
						$previous[$neighbour] = $current_entry;
					}
				}
			}
		}
		//now rebuild path
		$path = [];
		$last_link = $final_link;
		while ($previous->contains($last_link) === true) {
			array_unshift($path, $last_link);
			$last_link = $previous[$last_link];
		}
		array_unshift($path, $last_link);

		return $path;
	}


	private function cleanCoveredEntries(string $entry_key, array &$covered_entries, array &$covered_for_current_root) {
		if (isset($covered_for_current_root[$entry_key]) === false) {
			return;
		}
		foreach ($covered_for_current_root[$entry_key] as $related_entry => $_) {
			unset($covered_entries[$related_entry]);
			$this->cleanCoveredEntries($related_entry, $covered_entries, $covered_for_current_root);
			unset($covered_for_current_root[$related_entry]);
		}
	}
}