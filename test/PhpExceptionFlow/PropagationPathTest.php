<?php
namespace PhpExceptionFlow;

use PhpExceptionFlow\Scope\Scope;

class PropagationPathTest extends \PHPUnit_Framework_TestCase {

	public function testFromInitialCall() {
		$scope = $this->createMock(Scope::class);
		$path = PropagationPath::fromInitialScope($scope);
		$this->assertEquals([$scope], $path->getScopeChain());
	}

	public function testAddCallDoesNotAffectOriginalPropagationPath() {
		$scope = $this->createMock(Scope::class);
		$callee_scope = $this->createMock(Scope::class);

		$path = PropagationPath::fromInitialScope($scope);
		$new_path = $path->addCall($scope, $callee_scope);
		$this->assertEquals([$scope], $path->getScopeChain());
		$this->assertEquals([$scope, $callee_scope], $new_path->getScopeChain());
	}

	public function testContainsCall() {
		$scope_1 = $this->createMock(Scope::class);
		$scope_2 = $this->createMock(Scope::class);
		$scope_3 = $this->createMock(Scope::class);

		$path = PropagationPath::fromInitialScope($scope_1);
		$path = $path->addCall($scope_1, $scope_2);
		$path = $path->addCall($scope_2, $scope_3);

		$this->assertTrue($path->lastOcccurrencesOfScopesAreCallingEachother($scope_1, $scope_2));
		$this->assertFalse($path->lastOcccurrencesOfScopesAreCallingEachother($scope_1, $scope_3));
		$this->assertTrue($path->lastOcccurrencesOfScopesAreCallingEachother($scope_2, $scope_3));
	}

}