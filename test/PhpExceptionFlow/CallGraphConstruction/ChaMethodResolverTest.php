<?php

namespace PhpExceptionFlow\CallGraphConstruction;

use PhpExceptionFlow\Collection\PartialOrderInterface;

class ChaMethodResolverTest extends \PHPUnit_Framework_TestCase {

	/** @var ChaMethodResolver */
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
		$this->resolver = new ChaMethodResolver($this->resolved_by);
	}

	public function testWithEmptyPartialOrderReturnsEmpty() {
		$partial_order_mock = $this->createMock(PartialOrderInterface::class);

		$partial_order_mock->expects($this->once())
			->method("getMaximalElements")
			->willReturn(array());
		$this->assertEmpty($this->resolver->fromPartialOrder($partial_order_mock));
	}

	public function testWithOneMethodInTopLevelClass() {
		$method_a_m = $this->buildMethodMock('a', 'm');
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
		$method_a_m = $this->buildMethodMock('a', 'm');
		$method_c_m = $this->buildMethodMock('c', 'm'); //overrides a->m
		$method_d_m = $this->buildMethodMock('d', 'm'); //overrides c->m
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
		$method_a_m = $this->buildMethodMock('a', 'm');
		$method_c_m = $this->buildMethodMock('c', 'm'); //overrides a->m
		$method_d_m = $this->buildMethodMock('d', 'm'); //overrides c->m
		$method_e_m = $this->buildMethodMock('e', 'm');
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




	/**
	 * @param string $class
	 * @param string $method_name
	 * @throws \PHPUnit_Framework_Exception
	 * @return Method
	 */
	private function buildMethodMock($class, $method_name) {
		$method_mock = $this->createMock(Method::class);
		$method_mock->method('getClass')->willReturn($class);
		$method_mock->method('getName')->willReturn($method_name);
		return $method_mock;
	}
}