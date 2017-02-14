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

	public function testUnimplementedMethodsMapsToAllImplementingMethods() {
		$method_a_m = $this->buildMethodMock("a", "m", false, false);
		$method_b_m = $this->buildMethodMock("b", "m", true, false);
		$method_c_m = $this->buildMethodMock("c", "m", true, false);

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
				"b" => "b",
				"c" => "c",
			],
		];
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

		$partial_order = $this->createMock(PartialOrderInterface::class);
		$partial_order->expects($this->once())
			->method("getMaximalElements")
			->willReturn(array($method_a_m));

		$partial_order->expects($this->exactly(3))
			->method("getDescendants")
			->withConsecutive(
				[$method_a_m],
				[$method_b_m])
			->will($this->onConsecutiveCalls(
				[$method_b_m, $method_c_m],
				[$method_c_m],
				[]
			));

		$partial_order->expects($this->exactly(3))
			->method("getChildren")
			->withConsecutive(
				[$method_a_m],
				[$method_b_m],
				[$method_c_m])
			->will($this->onConsecutiveCalls(
				[$method_b_m],
				[$method_c_m],
				[]
			));

		$partial_order->expects($this->exactly(3))
			->method("getParents")
			->withConsecutive(
				[$method_a_m],
				[$method_b_m],
				[$method_c_m])
			->will($this->onConsecutiveCalls(
				[],
				[$method_a_m],
				[$method_b_m]
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
		$this->assertCount(1, $class_method_to_method["b"]["m"]);
		$this->assertCount(0, $class_method_to_method["c"]["m"]); // c->m has no overriding classes

		$this->assertEquals("b", $class_method_to_method["a"]["m"][0]->getClass());
		$this->assertEquals("c", $class_method_to_method["a"]["m"][1]->getClass());

		$this->assertEquals("c", $class_method_to_method["b"]["m"][0]->getClass());
	}

	public function testUnimplementedMethodResolveToImplementedInComplexerHierarchy() {
		// hierarchy:
		//          a (interface)
		//          |
		//          b (abstract, but implements n)
		//       /     \
		//      c       d
		//c inherits n, implements m. d implements both m and n
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
				"b" => "b",
				"c" => "c",
			],
			"d" => [
				"a" => "a",
				"b" => "b",
				"d" => "d",
			],
		];
		$this->state->classResolvedBy = [
			"a" => [
				"a" => "a",
				"b" => "b",
				"c" => "c",
				"d" => "d",
			],
			"b" => [
				"b" => "b",
				"c" => "c",
				"d" => "d",
			],
			"c" => [
				"c" => "c",
			],
			"d" => [
				"d" => "d",
			],
		];



		$method_a_m = $this->buildMethodMock("a", "m", false, false);
		$method_a_n = $this->buildMethodMock("a", "n", false, false);
		$method_b_m = $this->buildMethodMock("b", "m", false, false);
		$method_b_n = $this->buildMethodMock("b", "n", true, false);
		$method_c_m = $this->buildMethodMock("c", "m", true, false);
		$method_d_m = $this->buildMethodMock("d", "m", true, false);
		$method_d_n = $this->buildMethodMock("d", "n", true, false);

		$partial_order = $this->createMock(PartialOrderInterface::class);
		$partial_order->expects($this->once())
			->method("getMaximalElements")
			->willReturn(array($method_a_m, $method_a_n));

		$partial_order->expects($this->exactly(7))
			->method("getDescendants")
			->withConsecutive(
				[$method_a_m],
				[$method_a_n],
				[$method_b_m],
				[$method_b_n],
				[$method_c_m],
				[$method_d_m],
				[$method_d_n]
			)
			->will($this->onConsecutiveCalls(
				[$method_b_m, $method_c_m, $method_d_m],
				[$method_b_n, $method_d_n],
				[$method_c_m, $method_d_m],
				[$method_d_n],
				[],
				[],
				[]
			));

		$partial_order->expects($this->exactly(7))
			->method("getParents")
			->withConsecutive(
				[$method_a_m],
				[$method_a_n],
				[$method_b_m],
				[$method_b_n],
				[$method_c_m],
				[$method_d_m],
				[$method_d_n])
			->will($this->onConsecutiveCalls(
				[],
				[],
				[$method_a_m],
				[$method_a_n],
				[$method_b_m],
				[$method_b_m],
				[$method_b_n]
			));

		$partial_order->expects($this->exactly(7))
			->method("getChildren")
			->withConsecutive(
				[$method_a_m],
				[$method_a_n],
				[$method_b_m],
				[$method_b_n],
				[$method_c_m],
				[$method_d_m],
				[$method_d_n])
			->will($this->onConsecutiveCalls(
				[$method_b_m],
				[$method_b_n],
				[$method_c_m, $method_d_m],
				[$method_d_n],
				[],
				[],
				[]
			));
		$class_method_to_method = $this->resolver->fromPartialOrder($partial_order);

		foreach ($class_method_to_method["a"]["m"] as $overriding) {
			print $overriding->getClass() . "\n";
		}



		$this->assertCount(4, $class_method_to_method);
		$this->assertArrayHasKey("a", $class_method_to_method);
		$this->assertArrayHasKey("b", $class_method_to_method);
		$this->assertArrayHasKey("c", $class_method_to_method);
		$this->assertArrayHasKey("d", $class_method_to_method);
		$this->assertCount(2, $class_method_to_method["a"]);
		$this->assertCount(2, $class_method_to_method["b"]);
		$this->assertCount(1, $class_method_to_method["c"]);
		$this->assertCount(2, $class_method_to_method["d"]);
		$this->assertArrayHasKey("m", $class_method_to_method["a"]);
		$this->assertArrayHasKey("n", $class_method_to_method["a"]);
		$this->assertCount(2, $class_method_to_method["a"]["m"]);
		$this->assertCount(2, $class_method_to_method["a"]["n"]);
		$this->assertArrayHasKey("m", $class_method_to_method["b"]);
		$this->assertArrayHasKey("n", $class_method_to_method["b"]);
		$this->assertCount(2, $class_method_to_method["b"]["m"]);
		$this->assertCount(1, $class_method_to_method["b"]["n"]);
		$this->assertArrayHasKey("m", $class_method_to_method["c"]);
		$this->assertCount(0, $class_method_to_method["c"]["m"]);
		$this->assertArrayHasKey("m", $class_method_to_method["d"]);
		$this->assertArrayHasKey("n", $class_method_to_method["d"]);
		$this->assertCount(0, $class_method_to_method["d"]["m"]);
		$this->assertCount(0, $class_method_to_method["d"]["n"]);

		$this->assertEquals("c", $class_method_to_method["a"]["m"][0]->getClass());
		$this->assertEquals("d", $class_method_to_method["a"]["m"][1]->getClass());
		$this->assertEquals("b", $class_method_to_method["a"]["n"][0]->getClass());
		$this->assertEquals("d", $class_method_to_method["a"]["n"][1]->getClass());
		$this->assertEquals("c", $class_method_to_method["b"]["m"][0]->getClass());
		$this->assertEquals("d", $class_method_to_method["b"]["m"][1]->getClass());
		$this->assertEquals("d", $class_method_to_method["b"]["n"][0]->getClass());
	}

	public function testPrivateMethodsAreNotOverridden() {
		$method_a_m = $this->buildMethodMock("a", "m", true, true);
		$method_b_m = $this->buildMethodMock("b", "m", true, false);
		$method_c_m = $this->buildMethodMock("c", "m", true, false);

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
				"b" => "b",
				"c" => "c",
			],
		];
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

		$partial_order = $this->createMock(PartialOrderInterface::class);

		$partial_order->expects($this->once())
			->method("getMaximalElements")
			->willReturn(array($method_a_m));

		$partial_order->expects($this->exactly(3))
			->method("getChildren")
			->withConsecutive(
				[$method_a_m],
				[$method_b_m],
				[$method_c_m]
			)
			->will($this->onConsecutiveCalls(
				[$method_b_m],
				[$method_c_m],
				[]
			));

		$partial_order->expects($this->exactly(3))
			->method("getParents")
			->withConsecutive(
				[$method_a_m],
				[$method_b_m],
				[$method_c_m]
			)
			->will($this->onConsecutiveCalls(
				[],
				[$method_b_m],
				[$method_c_m]
			));


		$partial_order->expects($this->exactly(2))
			->method("getDescendants")
			->withConsecutive(
				[$method_b_m],
				[$method_c_m]
			)
			->will($this->onConsecutiveCalls(
				[$method_c_m],
				[]
			));

		$class_method_to_method = $this->resolver->fromPartialOrder($partial_order);

		print_r(array_keys($class_method_to_method));

		$this->assertCount(3, $class_method_to_method);
		$this->assertArrayHasKey("a", $class_method_to_method);
		$this->assertArrayHasKey("b", $class_method_to_method);
		$this->assertArrayHasKey("c", $class_method_to_method);
		$this->assertCount(1, $class_method_to_method["a"]);
		$this->assertCount(1, $class_method_to_method["b"]);
		$this->assertCount(1, $class_method_to_method["c"]);
		$this->assertArrayHasKey("m", $class_method_to_method["a"]);
		$this->assertArrayHasKey("m", $class_method_to_method["b"]);
		$this->assertArrayHasKey("m", $class_method_to_method["c"]);
		$this->assertCount(0, $class_method_to_method["a"]["m"]);
		$this->assertCount(1, $class_method_to_method["b"]["m"]);
		$this->assertCount(0, $class_method_to_method["c"]["m"]);

		$this->assertEquals("c", $class_method_to_method["b"]["m"][0]->getClass());
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