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
		$guarded_enclosing_next_2 = new GuardedScope($this->createMock(Scope::class), $next_2);
		$initial_link = new Raises($origin);
		$propagates_1 = new Propagates($origin, $next_1);
		$propagates_2 = new Propagates($origin, $next_2);
		$catches_2 = new Catches($guarded_enclosing_next_2, $this->createMock(Catch_::class));

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



	public function testPathsUntilCatchClause() {
		$scope_a = new Scope("a");
		$scope_b = new Scope("b");
		$scope_c = new Scope("c");
		$scope_d = new Scope("d");
		$guarding_d = new GuardedScope($this->createMock(Scope::class), $scope_d);

		$raises_a = new Raises($scope_a);
		$propagates_a_b = new Propagates($scope_a, $scope_b);
		$propagates_a_d = new Propagates($scope_a, $scope_d);
		$propagates_b_a = new Propagates($scope_b, $scope_a);
		$propagates_b_c = new Propagates($scope_b, $scope_c);
		$propagates_b_d = new Propagates($scope_b, $scope_d);
		$propagates_c_d = new Propagates($scope_c, $scope_d);
		$catches_d = new Catches($guarding_d, $this->createMock(Catch_::class));

		$path_collection = new PathCollection($raises_a);
		$path_collection->addEntry($propagates_a_b);
		$path_collection->addEntry($propagates_a_d);
		$path_collection->addEntry($propagates_b_a); //introduces cycle, but is ignored.
		$path_collection->addEntry($propagates_b_c);
		$path_collection->addEntry($propagates_b_d);
		$path_collection->addEntry($propagates_c_d);
		$path_collection->addEntry($catches_d);

		$paths = [];
		foreach ($path_collection->getPathsEndingInLink($catches_d) as $path) {
			$paths[] = $path;
		}
		$this->assertCount(3, $paths);
		$this->assertEquals([$raises_a, $propagates_a_d, $catches_d], $paths[0]);
		$this->assertEquals([$raises_a, $propagates_a_b, $propagates_b_d, $catches_d], $paths[1]);
		$this->assertEquals([$raises_a, $propagates_a_b, $propagates_b_c, $propagates_c_d, $catches_d], $paths[2]);
	}
}