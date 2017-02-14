<?php

namespace PhpExceptionFlow\CallGraphConstruction;

use PhpExceptionFlow\Collection\PartialOrderInterface;

class AppliesToMethodResolverTest extends \PHPUnit_Framework_TestCase {

	/** @var AppliesToMethodResolver */
	private $resolver;

	/*
	 * resolved_by represents the following class hierarchy:
	 *          a           e
	 *        /  \          |
	 *        b   c         f
	 *            |
	 *            d
	 */

	private $resolved_by = [
		"a" => [
			"a" => "a",
			"b" => "b",
			"c" => "c",
			"d" => "d",
		],
		"b" => [
			"b" => "b",
		],
		"c" => [
			"c" => "c",
			"d" => "d",
		],
		"d" => [
			"d" => "d",
		],
		"e" => [
			"e" => "e",
			"f" => "f",
		],
		"f" => [
			"f" => "f",
		],
	];


	public function setUp() {
		$this->resolver = new AppliesToMethodResolver($this->resolved_by);
	}

	public function testWithEmptyPartialOrderReturnsEmpty() {
		$partial_order_mock = $this->createMock(PartialOrderInterface::class);

		$partial_order_mock->expects($this->once())
			->method("getMaximalElements")
			->willReturn(array());
		$this->assertEmpty($this->resolver->fromPartialOrder($partial_order_mock));
	}

	public function testWithOneMethodInTopLevelClass() {
		$method_a_m = $this->buildMethodMock('a', 'm', true, false);
		$partial_order_mock = $this->createMock(PartialOrderInterface::class);
		$partial_order_mock->expects($this->once())
			->method("getMaximalElements")
			->willReturn(array($method_a_m));
		$partial_order_mock->expects($this->exactly(2)) //one time for queuing next methods, one time for getting overriding methods for applies_to
			->method("getChildren")
			->with($method_a_m)
			->willReturn(array());

		$class_method_map = $this->resolver->fromPartialOrder($partial_order_mock);

		$this->assertEquals("a", $class_method_map["a"]["m"][0]->getClass());
		$this->assertEquals("a", $class_method_map["b"]["m"][0]->getClass());
		$this->assertEquals("a", $class_method_map["c"]["m"][0]->getClass());
		$this->assertEquals("a", $class_method_map["d"]["m"][0]->getClass());
	}

	public function testWithOverridingMethod() {
		$method_a_m = $this->buildMethodMock('a', 'm', true, false);
		$method_c_m = $this->buildMethodMock('c', 'm', true, false); //overrides a->m
		$method_d_m = $this->buildMethodMock('d', 'm', true, false); //overrides c->m
		$partial_order_mock = $this->createMock(PartialOrderInterface::class);
		$partial_order_mock->expects($this->once())
			->method("getMaximalElements")
			->willReturn([$method_a_m]);

		$partial_order_mock->expects($this->exactly(6))
			->method("getChildren")
			->withConsecutive(
				[$method_a_m],
				[$method_a_m],
				[$method_c_m],
				[$method_c_m],
				[$method_d_m],
				[$method_d_m]
			)
			->will($this->onConsecutiveCalls(
				[$method_c_m],
				[$method_c_m],
				[$method_d_m],
				[$method_d_m],
				[],
				[]
			));


		$class_method_map = $this->resolver->fromPartialOrder($partial_order_mock);
		$this->assertEquals("a", $class_method_map["a"]["m"][0]->getClass());
		$this->assertEquals("a", $class_method_map["b"]["m"][0]->getClass());
		$this->assertEquals("c", $class_method_map["c"]["m"][0]->getClass());
		$this->assertEquals("d", $class_method_map["d"]["m"][0]->getClass());
	}

	public function testWithMultipleTopLevelElementsMethod() {
		$method_a_m = $this->buildMethodMock('a', 'm', true, false);
		$method_c_m = $this->buildMethodMock('c', 'm', true, false); //overrides a->m
		$method_d_m = $this->buildMethodMock('d', 'm', true, false); //overrides c->m
		$method_e_m = $this->buildMethodMock('e', 'm', true, false);
		$partial_order_mock = $this->createMock(PartialOrderInterface::class);
		$partial_order_mock->expects($this->once())
			->method("getMaximalElements")
			->willReturn([$method_a_m, $method_e_m]);

		$partial_order_mock->expects($this->exactly(8))
		->method("getChildren")
			->withConsecutive(
				[$method_a_m],
				[$method_a_m],
				[$method_e_m],
				[$method_e_m],
				[$method_c_m],
				[$method_c_m],
				[$method_d_m],
				[$method_d_m]
			)
			->will($this->onConsecutiveCalls(
				[$method_c_m],
				[$method_c_m],
				[],
				[],
				[$method_d_m],
				[$method_d_m],
				[],
				[]
			));


		$class_method_map = $this->resolver->fromPartialOrder($partial_order_mock);
		$this->assertEquals("a", $class_method_map["a"]["m"][0]->getClass());
		$this->assertEquals("a", $class_method_map["b"]["m"][0]->getClass());
		$this->assertEquals("c", $class_method_map["c"]["m"][0]->getClass());
		$this->assertEquals("d", $class_method_map["d"]["m"][0]->getClass());
		$this->assertEquals("e", $class_method_map["e"]["m"][0]->getClass());
		$this->assertEquals("e", $class_method_map["f"]["m"][0]->getClass());
	}

	public function testUnimplementedMethodDoesNotApplyToAnyOtherMethod() {
		$method_a_m = $this->buildMethodMock('a', 'm', false, false); //unimplemented
		$method_d_m = $this->buildMethodMock('d', 'm', true, false);

		$partial_order_mock = $this->createMock(PartialOrderInterface::class);
		$partial_order_mock->expects($this->once())
			->method("getMaximalElements")
			->willReturn([$method_a_m]);

		$partial_order_mock->expects($this->exactly(3))
			->method("getChildren")
			->withConsecutive(
				[$method_a_m],
				[$method_d_m],
				[$method_d_m])
			->will($this->onConsecutiveCalls(
				[$method_d_m],
				[],
				[]
			));

		$class_method_map = $this->resolver->fromPartialOrder($partial_order_mock);

		$this->assertCount(1, $class_method_map);
		$this->assertEquals("d", $class_method_map["d"]["m"][0]->getClass());
	}

	public function testPrivateMethodOnlyAppliesToClassItIsDefinedIn() {
		$method_a_m = $this->buildMethodMock('a', 'm', true, true); //private
		$method_c_m = $this->buildMethodMock('c', 'm', true, false);

		$partial_order_mock = $this->createMock(PartialOrderInterface::class);
		$partial_order_mock->expects($this->once())
			->method("getMaximalElements")
			->willReturn([$method_a_m]);

		$partial_order_mock->expects($this->exactly(3))
			->method("getChildren")
			->withConsecutive(
				[$method_a_m],
				[$method_c_m],
				[$method_c_m])
			->will($this->onConsecutiveCalls(
				[$method_c_m],
				[],
				[]
			));

		$class_method_map = $this->resolver->fromPartialOrder($partial_order_mock);

		$this->assertEquals("a", $class_method_map["a"]["m"][0]->getClass());
		$this->assertEquals("c", $class_method_map["c"]["m"][0]->getClass());
		$this->assertEquals("c", $class_method_map["d"]["m"][0]->getClass());
		$this->assertCount(3, $class_method_map);
	}

	/**
	 * @param string $class
	 * @param string $method_name
	 * @param bool $is_implemented
	 * @param bool $is_private
	 * @throws \PHPUnit_Framework_Exception
	 * @return Method
	 */
	private function buildMethodMock($class, $method_name, bool $is_implemented, bool $is_private) {
		$method_mock = $this->createMock(Method::class);
		$method_mock->method('getClass')->willReturn($class);
		$method_mock->method('getName')->willReturn($method_name);
		$method_mock->method('isImplemented')->willReturn($is_implemented);
		$method_mock->method('isPrivate')->willReturn($is_private);
		return $method_mock;
	}
}