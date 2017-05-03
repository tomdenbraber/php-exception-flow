<?php
namespace PhpExceptionFlow\Path;

use PhpExceptionFlow\Scope\GuardedScope;
use PhpExceptionFlow\Scope\Scope;
use PhpParser\Node\Stmt\Catch_;

class PathCollectionTest extends \PHPUnit_Framework_TestCase {

	public function testWithOnlyOriginReturnsOnePath() {
		$origin = new Scope("a");
		$initial_link = new Raises($origin);
		$path_collection = new PathCollection($initial_link);

		$paths = $path_collection->getPaths();
		$this->assertCount(1, $paths);
		$this->assertEquals([$initial_link], $paths[0]);
	}

	public function testContainsEntryReturnsTrueWhenEntryIsPresent() {
		$origin = new Scope("a");
		$next = new Scope("b");
		$initial_link = new Raises($origin);
		$propagates = new Propagates($origin, $next);
		$path_collection = new PathCollection($initial_link);
		$path_collection->addEntry($propagates);

		$this->assertTrue($path_collection->containsEntry(new Propagates($origin, $next)));
	}

	public function testContainsEntryReturnsFalseWhenEntryIsNotPresent() {
		$origin = new Scope("a");
		$next = new Scope("b");
		$initial_link = new Raises($origin);
		$path_collection = new PathCollection($initial_link);

		$this->assertFalse($path_collection->containsEntry(new Propagates($origin, $next)));
	}

	public function testForEachAdditioAUniquePathIsPresent() {
		$origin = new Scope("a");
		$next_1 = new Scope("b");
		$next_2 = new Scope("c");
		$initial_link = new Raises($origin);
		$propagates_1 = new Propagates($origin, $next_1);
		$propagates_2 = new Propagates($origin, $next_2);
		$path_collection = new PathCollection($initial_link);
		$path_collection->addEntry($propagates_1);
		$path_collection->addEntry($propagates_2);

		$paths = $path_collection->getPaths();
		$this->assertCount(3, $paths);
		$this->assertEquals([$initial_link], $paths[0]);
		$this->assertEquals([$initial_link, $propagates_1], $paths[1]);
		$this->assertEquals([$initial_link, $propagates_2], $paths[2]);
	}

	public function testChainingLinks() {
		$origin = new Scope("a");
		$next_1 = new Scope("b");
		$next_2 = new Scope("c");
		$gs_1 = new GuardedScope($next_2, $next_1);
		$initial_link = new Raises($origin);
		$propagates_1 = new Propagates($origin, $next_1);
		$uncaught_1 = new Uncaught($gs_1, $next_2);
		$path_collection = new PathCollection($initial_link);
		$path_collection->addEntry($propagates_1);
		$path_collection->addEntry($uncaught_1);

		$paths = $path_collection->getPaths();
		$this->assertCount(3, $paths);
		$this->assertEquals([$initial_link], $paths[0]);
		$this->assertEquals([$initial_link, $propagates_1], $paths[1]);
		$this->assertEquals([$initial_link, $propagates_1, $uncaught_1], $paths[2]);
	}

	public function testWithCatches() {
		$origin = new Scope("a");
		$next_1 = new Scope("b");
		$next_2 = new Scope("c");
		$initial_link = new Raises($origin);
		$propagates_1 = new Propagates($origin, $next_1);
		$propagates_2 = new Propagates($origin, $next_2);
		$catches_2 = new Catches($next_2, $this->createMock(Catch_::class));

		$path_collection = new PathCollection($initial_link);
		$path_collection->addEntry($propagates_1);
		$path_collection->addEntry($propagates_2);
		$path_collection->addEntry($catches_2);

		$paths = $path_collection->getPaths();
		$this->assertCount(4, $paths);
		$this->assertEquals([$initial_link], $paths[0]);
		$this->assertEquals([$initial_link, $propagates_1], $paths[1]);
		$this->assertEquals([$initial_link, $propagates_2], $paths[2]);
		$this->assertEquals([$initial_link, $propagates_2, $catches_2], $paths[3]);
	}

	public function testDoublePathOnlyShowsUpOnce() {
		$origin = new Scope("a");
		$next_1 = new Scope("b");
		$next_2 = new Scope("c");
		$initial_link = new Raises($origin);
		$propagates_1 = new Propagates($origin, $next_1);
		$propagates_2 = new Propagates($origin, $next_2);
		$propagates_3 = new Propagates($next_2, $next_1);
		$path_collection = new PathCollection($initial_link);
		$path_collection->addEntry($propagates_1);
		$path_collection->addEntry($propagates_2);
		$path_collection->addEntry($propagates_3);

		$paths = $path_collection->getPaths();
		$this->assertCount(4, $paths);
		$this->assertEquals([$initial_link], $paths[0]);
		$this->assertEquals([$initial_link, $propagates_1], $paths[1]);
		$this->assertEquals([$initial_link, $propagates_2], $paths[2]);
		$this->assertEquals([$initial_link, $propagates_2, $propagates_3], $paths[3]);
	}
}