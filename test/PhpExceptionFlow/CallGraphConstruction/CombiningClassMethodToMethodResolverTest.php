<?php

namespace PhpExceptionFlow\CallGraphConstruction;

use PhpExceptionFlow\Collection\PartialOrderInterface;

class CombiningClassMethodToMethodResolverTest extends \PHPUnit_Framework_TestCase {
	/** @var CombiningClassMethodToMethodResolver */
	private $resolver;

	public function setUp() {
		$this->resolver = new CombiningClassMethodToMethodResolver();
	}

	public function testWithoutResolvers() {
		$partial_order = $this->createMock(PartialOrderInterface::class);
		$this->assertEmpty($this->resolver->fromPartialOrder($partial_order));
	}

	public function testWithEmptyResolvers() {
		$partial_order = $this->createMock(PartialOrderInterface::class);
		$resolver_1 = $this->createMock(MethodCallToMethodResolverInterface::class);
		$resolver_2 = $this->createMock(MethodCallToMethodResolverInterface::class);

		$resolver_1->expects($this->once())
			->method("fromPartialOrder")
			->willReturn(array());
		$resolver_2->expects($this->once())
			->method("fromPartialOrder")
			->willReturn(array());

		$this->resolver->addResolver($resolver_1);
		$this->resolver->addResolver($resolver_2);

		$this->assertEmpty($this->resolver->fromPartialOrder($partial_order));
	}

	public function testCombiningClassesGoesWell() {
		$partial_order = $this->createMock(PartialOrderInterface::class);
		$partial_order = $this->createMock(PartialOrderInterface::class);
		$resolver_1 = $this->createMock(MethodCallToMethodResolverInterface::class);
		$resolver_2 = $this->createMock(MethodCallToMethodResolverInterface::class);

		$resolver_1->expects($this->once())
			->method("fromPartialOrder")
			->willReturn(
				[
					"a" => [
						"m" => ["x"],
						"n" => ["z"]
					],
				]
			);
		$resolver_2->expects($this->once())
			->method("fromPartialOrder")
			->willReturn(
				[
					"a" => [
						"m" => ["y"],
						"o" => ["quux"],
					],
				]
			);

		$this->resolver->addResolver($resolver_1);
		$this->resolver->addResolver($resolver_2);

		$this->assertEquals(
			[
				"a" => [
					"m" => ["x", "y"],
					"n" => ["z"],
					"o" => ["quux"],
				],
			],
			$this->resolver->fromPartialOrder($partial_order)
		);
	}

	public function testOutputCombinesOutputOfWrappedResolvers() {
		$partial_order = $this->createMock(PartialOrderInterface::class);
		$resolver_1 = $this->createMock(MethodCallToMethodResolverInterface::class);
		$resolver_2 = $this->createMock(MethodCallToMethodResolverInterface::class);

		$resolver_1->expects($this->once())
			->method("fromPartialOrder")
			->willReturn(
				[
					"a" => [
						"m" => ["x", "y"]
					],
					"c" => [
						"f" => ["g"]
					]
				]
			);
		$resolver_2->expects($this->once())
			->method("fromPartialOrder")
			->willReturn(
				[
					"a" => [
						"m" => ["y"]
					],
					"b" => [
						"k" => ["b", "c"]
					]
				]
			);

		$this->resolver->addResolver($resolver_1);
		$this->resolver->addResolver($resolver_2);

		$this->assertEquals(
			[
				"a" => [
					"m" => ["x", "y"]
				],
				"b" => [
					"k" => ["b", "c"]
				],
				"c" => [
					"f" => ["g"]
				],
			],
			$this->resolver->fromPartialOrder($partial_order)
		);
	}
}