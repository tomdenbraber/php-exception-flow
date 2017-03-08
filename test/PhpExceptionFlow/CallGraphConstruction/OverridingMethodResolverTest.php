<?php

namespace PhpExceptionFlow\CallGraphConstruction;

use PhpExceptionFlow\Collection\PartialOrderInterface;
use PHPTypes\State;

class OverridingMethodResolverTest extends \PHPUnit_Framework_TestCase {
	/** @var OverridingMethodResolver */
	private $resolver;
	/** @var State $state */
	private $state;

	public function setUp() {
		$this->state = $this->createMock(State::class);
		$this->resolver = new OverridingMethodResolver($this->state);
	}

	public function testWithEmptyOrderReturnsEmpty() {
		$partial_order = $this->createMock(PartialOrderInterface::class);
		$partial_order->expects($this->once())
			->method("getMaximalElements")
			->willReturn(array());
		$this->assertEmpty($this->resolver->fromPartialOrder($partial_order));
	}

	public function testMethodResolvesToItself() {
		$public_a_m = $this->buildMethodMock("a", "m", true, false);
		$private_a_n = $this->buildMethodMock("a", "n", true, true);

		$this->state->classResolves = [
			"a" => [
				"a" => "a"
			],
		];
		$this->state->classResolvedBy = [
			"a" => [
				"a" => "a"
			],
		];


		$partial_order = $this->createMock(PartialOrderInterface::class);
		$partial_order->expects($this->once())
			->method("getMaximalElements")
			->willReturn(array($public_a_m, $private_a_n));

		$partial_order->method('getChildren')
			->willReturn(array());
		$partial_order->method('getParents')
			->willReturn(array());
		$partial_order->method('getAncestors')
			->willReturn(array());

		$class_method_to_method = $this->resolver->fromPartialOrder($partial_order);
		$this->assertCount(1, $class_method_to_method);
		$this->assertArrayHasKey("a", $class_method_to_method);
		$this->assertCount(2, $class_method_to_method["a"]);
		$this->assertArrayHasKey("m", $class_method_to_method["a"]);
		$this->assertArrayHasKey("n", $class_method_to_method["a"]);
		$this->assertCount(1, $class_method_to_method["a"]["m"]);
		$this->assertCount(1, $class_method_to_method["a"]["n"]);
		$this->assertEquals("a", $class_method_to_method["a"]["m"][0]->getClass());
		$this->assertEquals("a", $class_method_to_method["a"]["n"][0]->getClass());
	}

	public function testPrivateMethodIsNotInherited() {
		$private_a_m = $this->buildMethodMock("a", "m", true, true);

		$this->state->classResolves = [
			"a" => [
				"a" => "a"
			],
			"b" => [
				"a" => "a",
				"b" => "b",
			]
		];
		$this->state->classResolvedBy = [
			"a" => [
				"a" => "a",
				"b" => "b",
			],
			"b" => [
				"b" => "b",
			],
		];

		$partial_order = $this->createMock(PartialOrderInterface::class);
		$partial_order->expects($this->once())
			->method("getMaximalElements")
			->willReturn(array($private_a_m));

		$partial_order->method('getChildren')
			->willReturn(array());
		$partial_order->method('getParents')
			->willReturn(array());
		$partial_order->method('getAncestors')
			->willReturn(array());

		$class_method_to_method = $this->resolver->fromPartialOrder($partial_order);
		$this->assertCount(1, $class_method_to_method);
		$this->assertArrayHasKey("a", $class_method_to_method);
		$this->assertCount(1, $class_method_to_method["a"]);
		$this->assertArrayHasKey("m", $class_method_to_method["a"]);
		$this->assertCount(1, $class_method_to_method["a"]["m"]);
		$this->assertEquals("a", $class_method_to_method["a"]["m"][0]->getClass());
	}


	public function testUnimplementedResolvesToAllImplementingClassesImplementations() {
		$public_a_m = $this->buildMethodMock("a", "m", false, false);
		$public_b_m = $this->buildMethodMock("b", "m", true, false);
		$public_c_m = $this->buildMethodMock("c", "m", true, false);

		$this->state->classResolvedBy = [
			"a" => [
				"a" => "a",
				"b" => "b",
				"c" => "c",
			],
			"b" => [
				"b" => "b",
			],
			"c" => [
				"c" => "c",
			],
		];
		$this->state->classResolves = [
			"a" => [
				"a" => "a",
			],
			"b" => [
				"a" => "a",
				"b" => "b",
			],
			"c" => [
				"a" => "a",
				"c" => "c",
			],
		];

		$partial_order = $this->createMock(PartialOrderInterface::class);
		$partial_order->expects($this->once())
			->method("getMaximalElements")
			->willReturn(array($public_a_m));

		$partial_order->method('getChildren')
			->withConsecutive(
				[$public_a_m],
				[$public_a_m],
				[$public_b_m],
				[$public_b_m],
				[$public_c_m],
				[$public_c_m]
			)
			->will($this->onConsecutiveCalls(
				[$public_b_m, $public_c_m],
				[$public_b_m, $public_c_m],
				[],
				[],
				[],
				[],
				[]
			));

		$partial_order->expects($this->exactly(3))
			->method('getAncestors')
			->withConsecutive(
				[$public_a_m],
				[$public_b_m],
				[$public_c_m]
			)
			->will($this->onConsecutiveCalls(
				[],
				[$public_a_m],
				[$public_a_m]
			));


		$class_method_to_method = $this->resolver->fromPartialOrder($partial_order);
		$this->assertCount(3, $class_method_to_method);
		$this->assertArrayHasKey("a", $class_method_to_method);
		$this->assertArrayHasKey("b", $class_method_to_method);
		$this->assertArrayHasKey("c", $class_method_to_method);

		$this->assertCount(1, $class_method_to_method["a"]);
		$this->assertArrayHasKey("m", $class_method_to_method["a"]);
		$this->assertCount(1, $class_method_to_method["b"]);
		$this->assertArrayHasKey("m", $class_method_to_method["b"]);
		$this->assertCount(1, $class_method_to_method["c"]);
		$this->assertArrayHasKey("m", $class_method_to_method["c"]);

		$this->assertCount(2, $class_method_to_method["a"]["m"]);
		$this->assertEquals("b", $class_method_to_method["a"]["m"][0]->getClass());
		$this->assertEquals("c", $class_method_to_method["a"]["m"][1]->getClass());

		$this->assertCount(1, $class_method_to_method["b"]["m"]);
		$this->assertEquals("b", $class_method_to_method["b"]["m"][0]->getClass());

		$this->assertCount(1, $class_method_to_method["c"]["m"]);
		$this->assertEquals("c", $class_method_to_method["c"]["m"][0]->getClass());
	}

	public function testInterfaceMethodCanBeResolvedToTraitIfUsedInClass() {
		$public_a_m = $this->buildMethodMock("a", "m", false, false);
		$public_trait_t1_m = $this->buildMethodMock("trait_t1", "m", true, false);

		// b implements a and uses trait_t1

		$this->state->classResolvedBy = [
			"a" => [
				"a" => "a",
				"b" => "b",
			],
			"b" => [
				"b" => "b",
			],
			"trait_t1" => [
				"trait_t1" => "trait_t1",
				"b" => "b",
			],
		];
		$this->state->classResolves = [
			"a" => [
				"a" => "a",
			],
			"b" => [
				"a" => "a",
				"b" => "b",
				"trait_t1" => "trait_t1",
			],
			"trait_t1" => [
				"trait_t1" => "trait_t1",
			],
		];

		$partial_order = $this->createMock(PartialOrderInterface::class);
		$partial_order->expects($this->once())
			->method("getMaximalElements")
			->willReturn(array($public_a_m));

		$partial_order->expects($this->exactly(4))
			->method('getChildren')
			->withConsecutive(
				[$public_a_m],
				[$public_a_m],
				[$public_trait_t1_m],
				[$public_trait_t1_m]
			)
			->will($this->onConsecutiveCalls(
				[$public_trait_t1_m],
				[$public_trait_t1_m],
				[],
				[]
			));

		$partial_order->expects($this->exactly(2))
			->method('getAncestors')
			->withConsecutive(
				[$public_a_m],
				[$public_trait_t1_m])
			->will($this->onConsecutiveCalls(
				[],
				[$public_a_m]
			));

		$class_method_to_method = $this->resolver->fromPartialOrder($partial_order);
		$this->assertCount(3, $class_method_to_method);
		$this->assertArrayHasKey("a", $class_method_to_method);
		$this->assertArrayHasKey("b", $class_method_to_method);
		$this->assertArrayHasKey("trait_t1", $class_method_to_method);

		$this->assertCount(1, $class_method_to_method["a"]);
		$this->assertArrayHasKey("m", $class_method_to_method["a"]);
		$this->assertCount(1, $class_method_to_method["b"]);
		$this->assertArrayHasKey("m", $class_method_to_method["b"]);
		$this->assertCount(1, $class_method_to_method["trait_t1"]);
		$this->assertArrayHasKey("m", $class_method_to_method["trait_t1"]);

		$this->assertCount(1, $class_method_to_method["a"]["m"]);
		$this->assertEquals("trait_t1", $class_method_to_method["a"]["m"][0]->getClass());

		$this->assertCount(1, $class_method_to_method["b"]["m"]);
		$this->assertEquals("trait_t1", $class_method_to_method["b"]["m"][0]->getClass());

		$this->assertCount(1, $class_method_to_method["trait_t1"]["m"]);
		$this->assertEquals("trait_t1", $class_method_to_method["trait_t1"]["m"][0]->getClass());
	}

	public function testClassWithoutImplementationCanResolveToParentAndToSubClasses() {
		$public_a_m = $this->buildMethodMock("a", "m", true, false);
		$public_c_m = $this->buildMethodMock("c", "m", true, false);

		$this->state->classResolvedBy = [
			"a" => [
				"a" => "a",
				"b" => "b",
				"c" => "c",
			],
			"b" => [
				"b" => "b",
				"c" => "c",
			],
			"c" => [
				"c" => "c",
			],
		];
		$this->state->classResolves = [
			"a" => [
				"a" => "a",
			],
			"b" => [
				"a" => "a",
				"b" => "b",
			],
			"c" => [
				"a" => "a",
				"b" => "c",
				"c" => "c",
			],
		];

		$partial_order = $this->createMock(PartialOrderInterface::class);
		$partial_order->expects($this->once())
			->method("getMaximalElements")
			->willReturn(array($public_a_m));

		$partial_order->method('getChildren')
			->withConsecutive(
				[$public_a_m],
				[$public_a_m],
				[$public_c_m],
				[$public_c_m]
			)
			->will($this->onConsecutiveCalls(
				[$public_c_m],
				[$public_c_m],
				[],
				[]
			));

		$partial_order->expects($this->exactly(2))
			->method('getAncestors')
			->withConsecutive(
				[$public_a_m],
				[$public_c_m]
			)
			->will($this->onConsecutiveCalls(
				[],
				[$public_a_m]
			));


		$class_method_to_method = $this->resolver->fromPartialOrder($partial_order);
		$this->assertCount(3, $class_method_to_method);
		$this->assertArrayHasKey("a", $class_method_to_method);
		$this->assertArrayHasKey("b", $class_method_to_method);
		$this->assertArrayHasKey("c", $class_method_to_method);

		$this->assertCount(1, $class_method_to_method["a"]);
		$this->assertArrayHasKey("m", $class_method_to_method["a"]);
		$this->assertCount(1, $class_method_to_method["b"]);
		$this->assertArrayHasKey("m", $class_method_to_method["b"]);
		$this->assertCount(1, $class_method_to_method["b"]);
		$this->assertArrayHasKey("m", $class_method_to_method["b"]);

		$this->assertCount(2, $class_method_to_method["a"]["m"]);
		$this->assertEquals("a", $class_method_to_method["a"]["m"][0]->getClass());
		$this->assertEquals("c", $class_method_to_method["a"]["m"][1]->getClass());

		$this->assertCount(2, $class_method_to_method["b"]["m"]);
		$this->assertEquals("a", $class_method_to_method["b"]["m"][0]->getClass());
		$this->assertEquals("c", $class_method_to_method["b"]["m"][1]->getClass());

		$this->assertCount(1, $class_method_to_method["c"]["m"]);
		$this->assertEquals("c", $class_method_to_method["c"]["m"][0]->getClass());
	}

	public function testSuperClassThatDoesNotHaveACertainMethodWhichIsDefinedInSubclassDoesNotResolveIt() {
		$public_b_m = $this->buildMethodMock("b", "m", true, false);

		$this->state->classResolvedBy = [
			"a" => [
				"a" => "a",
				"b" => "b",
			],
			"b" => [
				"b" => "b",
			],
		];
		$this->state->classResolves = [
			"a" => [
				"a" => "a",
			],
			"b" => [
				"a" => "a",
				"b" => "b",
			],
		];

		$partial_order = $this->createMock(PartialOrderInterface::class);
		$partial_order->expects($this->once())
			->method("getMaximalElements")
			->willReturn(array($public_b_m));

		$partial_order->method("getChildren")
			->willReturn(array());
		$partial_order->method("getParents")
			->willReturn(array());
		$partial_order->method("getAncestors")
			->willReturn(array());


		$class_method_to_method = $this->resolver->fromPartialOrder($partial_order);
		$this->assertCount(1, $class_method_to_method);
		$this->assertArrayNotHasKey("a", $class_method_to_method);
		$this->assertArrayHasKey("b", $class_method_to_method);

		$this->assertCount(1, $class_method_to_method["b"]);
		$this->assertArrayHasKey("m", $class_method_to_method["b"]);

		$this->assertCount(1, $class_method_to_method["b"]["m"]);
		$this->assertEquals("b", $class_method_to_method["b"]["m"][0]->getClass());
	}

	public function testCorrectPrioritizationOfTraitMethods() {
		$public_a_m = $this->buildMethodMock("a", "m", true, false);
		$public_t1_m = $this->buildMethodMock("t1", "m", true, false);
		$public_b_m = $this->buildMethodMock("b", "m", true, false);

		$this->state->classResolvedBy = [
			"a" => [
				"a" => "a",
				"b" => "b",
			],
			"t1" => [
				"t1" => "t1",
				"b" => "b",
			],
			"b" => [
				"b" => "b",
			],
		];
		$this->state->classResolves = [
			"a" => [
				"a" => "a",
			],
			"t1" => [
				"t1" => "t1",
			],
			"b" => [
				"a" => "a",
				"t1" => "t1",
				"b" => "b",
			]
		];

		$partial_order = $this->createMock(PartialOrderInterface::class);
		$partial_order->expects($this->once())
			->method("getMaximalElements")
			->willReturn(array($public_a_m));

		$partial_order->method('getChildren')
			->withConsecutive(
				[$public_a_m],
				[$public_a_m],
				[$public_t1_m],
				[$public_t1_m],
				[$public_b_m],
				[$public_b_m]
			)
			->will($this->onConsecutiveCalls(
				[$public_t1_m],
				[$public_t1_m],
				[$public_b_m],
				[$public_b_m],
				[],
				[]
			));

		$partial_order->expects($this->exactly(3))
			->method('getAncestors')
			->withConsecutive(
				[$public_a_m],
				[$public_t1_m],
				[$public_b_m]
			)
			->will($this->onConsecutiveCalls(
				[],
				[],
				[$public_a_m, $public_t1_m]
			));


		$class_method_to_method = $this->resolver->fromPartialOrder($partial_order);
		$this->assertCount(3, $class_method_to_method);
		$this->assertArrayHasKey("a", $class_method_to_method);
		$this->assertArrayHasKey("t1", $class_method_to_method);
		$this->assertArrayHasKey("b", $class_method_to_method);

		$this->assertCount(1, $class_method_to_method["a"]);
		$this->assertArrayHasKey("m", $class_method_to_method["a"]);
		$this->assertCount(1, $class_method_to_method["t1"]);
		$this->assertArrayHasKey("m", $class_method_to_method["t1"]);
		$this->assertCount(1, $class_method_to_method["b"]);
		$this->assertArrayHasKey("m", $class_method_to_method["b"]);

		$this->assertCount(2, $class_method_to_method["a"]["m"]);
		$this->assertEquals("a", $class_method_to_method["a"]["m"][0]->getClass());
		$this->assertEquals("b", $class_method_to_method["a"]["m"][1]->getClass());

		$this->assertCount(2, $class_method_to_method["t1"]["m"]);
		$this->assertEquals("t1", $class_method_to_method["t1"]["m"][0]->getClass());
		$this->assertEquals("b", $class_method_to_method["t1"]["m"][1]->getClass());

		$this->assertCount(1, $class_method_to_method["b"]["m"]);
		$this->assertEquals("b", $class_method_to_method["b"]["m"][0]->getClass());

	}

	/**
	 * @param string $class
	 * @param string $method_name
	 * @param bool $implemented
	 * @param bool $is_private
	 * @throws \PHPUnit_Framework_Exception
	 * @return Method
	 */
	private function buildMethodMock(string $class, string $method_name, bool $implemented, bool $is_private) {
		$method_mock = $this->createMock(Method::class);
		$method_mock->method('getClass')->willReturn($class);
		$method_mock->method('getName')->willReturn($method_name);
		$method_mock->method('isImplemented')->willReturn($implemented);
		$method_mock->method('isPrivate')->willReturn($is_private);
		return $method_mock;
	}
}