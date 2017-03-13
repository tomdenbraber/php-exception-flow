<?php
namespace PhpExceptionFlow\Path;

use PhpExceptionFlow\Scope\GuardedScope;
use PhpExceptionFlow\Scope\Scope;

class PathTest extends \PHPUnit_Framework_TestCase {

	public function testFromInitialCall() {
		$scope = $this->createMock(Scope::class);
		$path = Path::fromInitialScope($scope);
		$this->assertEquals([new Raises($scope)], $path->getChain());
	}

	public function testAddCallDoesNotAffectOriginalPropagationPath() {
		$scope = $this->createMock(Scope::class);
		$callee_scope = $this->createMock(Scope::class);

		$path = Path::fromInitialScope($scope);
		$new_path = $path->addEntry(new Propagates($callee_scope));
		$this->assertEquals([new Raises($scope)], $path->getChain());
		$this->assertEquals([new Raises($scope), new Propagates($callee_scope)], $new_path->getChain());
	}

	public function testPathEqualsReturnsTrueOnSameEntriesInPath() {
		$scope_1 = $this->createMock(Scope::class);
		$scope_2 = $this->createMock(Scope::class);
		$scope_3 = $this->createMock(Scope::class);

		$gs_1 = $this->createMock(GuardedScope::class);

		$uncaught_1 = new Uncaught($scope_2, $gs_1);
		$propagates_1 = new Propagates($scope_3);
		$uncaught_2 = new Uncaught($scope_2, $gs_1);
		$propagates_2 = new Propagates($scope_3);


		$path_1 = Path::fromInitialScope($scope_1);
		$path_2 = Path::fromInitialScope($scope_1);
		$path_1 = $path_1->addEntry($uncaught_1);
		$path_2 = $path_2->addEntry($uncaught_2);
		$path_1 = $path_1->addEntry($propagates_1);
		$path_2 = $path_2->addEntry($propagates_2);

		$this->assertTrue($path_1->equals($path_2));
		$this->assertTrue($path_2->equals($path_1));
		$this->assertTrue($path_1->equals($path_1));
		$this->assertTrue($path_2->equals($path_2));
	}

	public function testPathEqualsReturnsFalseOnDifferentPaths() {
		$scope_1 = $this->createMock(Scope::class);
		$scope_2 = $this->createMock(Scope::class);
		$path_1 = Path::fromInitialScope($scope_1);
		$path_2 = Path::fromInitialScope($scope_2);
		$this->assertFalse($path_1->equals($path_2));
		$this->assertFalse($path_2->equals($path_1));
	}

}