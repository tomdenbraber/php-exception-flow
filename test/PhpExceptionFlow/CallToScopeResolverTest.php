<?php
namespace PhpExceptionFlow;

use PHPCfg\Op\Expr;
use PhpExceptionFlow\CHA\Method;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\PropertyFetch;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Name;
use PHPTypes\Type;

class CallToScopeResolverTest extends \PHPUnit_Framework_TestCase {

	private $method_a_m;
	private $method_c_m;
	private $method_e_m;
	private $method_f_k;
	private $scope_a_m;
	private $scope_c_m;
	private $scope_e_m;
	private $scope_f_k;
	private $scope_fn_m;
	private $scope_fn_k;

	private $applies_to;
	private $function_scopes;
	private $method_scopes;

	/** @var CallToScopeResolver $resolver */
	private $resolver;

	/**
	 * Class hierarchy used:
	 *          a[m]           e[m]
	 *        /  \             |
	 *        b   c[m]         f[k]
	 *            |
	 *            d
	 *
	 * on top of that, functions k and m are defined.
	 */
	public function setUp() {
		$this->method_a_m = $this->buildMethodMock("a", "m");
		$this->method_c_m = $this->buildMethodMock("c", "m");
		$this->method_e_m = $this->buildMethodMock("e", "m");
		$this->method_f_k = $this->buildMethodMock("f", "k");

		$this->scope_a_m = $this->buildScopeMock("a::m");
		$this->scope_c_m = $this->buildScopeMock("c::m");
		$this->scope_e_m = $this->buildScopeMock("e::m");
		$this->scope_f_k = $this->buildScopeMock("f::k");
		$this->scope_fn_k = $this->buildScopeMock("k");
		$this->scope_fn_m = $this->buildScopeMock("m");

		$this->applies_to = [
			"a" => [
				"m" => $this->method_a_m,
			],
			"b" => [
				"m" => $this->method_a_m,
			],
			"c" => [
				"m" => $this->method_c_m,
			],
			"d" => [
				"m" => $this->method_c_m,
			],
			"e" => [
				"m" => $this->method_e_m,
			],
			"f" => [
				"m" => $this->method_e_m,
				"k" => $this->method_f_k,
			]
		];
		$this->function_scopes = array(
			"k" => $this->scope_fn_k,
			"m" => $this->scope_fn_m,
		);
		$this->method_scopes = array(
			"a" => [
				"m" => $this->scope_a_m,
			],
			"c" => [
				"m" => $this->scope_c_m,
			],
			"e" => [
				"m" => $this->scope_e_m,
			],
			"f" => [
				"k" => $this->scope_f_k,
			]
		);
		$this->resolver = new CallToScopeResolver($this->method_scopes, $this->function_scopes, $this->applies_to);
	}

	public function testThrowsLogicExceptionOnWrongNodeType() {
		$not_a_call = $this->createMock(PropertyFetch::class);
		$this->expectException(\LogicException::class);
		$this->resolver->resolve($not_a_call);
	}

	public function testFunctionCallGetsResolvedToFunction() {
		$func_call_m = new FuncCall(new Name("m"));
		$resolved_to = $this->resolver->resolve($func_call_m);
		$this->assertEquals($this->scope_fn_m, $resolved_to);
	}

	public function testMethodCallToMethodInImplementingClass() {
		$var_expr = $this->createMock(\PhpParser\Node\Expr::class);
		$var_expr->method("getAttribute")
			->with("type", $this->anything())
			->willReturn(new Type(Type::TYPE_OBJECT, array(), "a"));

		$method_call_a_m = new MethodCall($var_expr, "m");
		$resolved_to = $this->resolver->resolve($method_call_a_m);
		$this->assertEquals($this->scope_a_m, $resolved_to);
	}

	public function testMethodCallToMethodInSubclassOfImplementingClass() {
		$var_expr = $this->createMock(\PhpParser\Node\Expr::class);
		$var_expr->method("getAttribute")
			->with("type", $this->anything())
			->willReturn(new Type(Type::TYPE_OBJECT, array(), "b"));

		$method_call_b_m = new MethodCall($var_expr, "m");
		$resolved_to = $this->resolver->resolve($method_call_b_m);
		$this->assertEquals($this->scope_a_m, $resolved_to);
	}

	public function testMethodCallToClassWhichOverridesAMethod() {
		$var_expr = $this->createMock(\PhpParser\Node\Expr::class);
		$var_expr->method("getAttribute")
			->with("type", $this->anything())
			->willReturn(new Type(Type::TYPE_OBJECT, array(), "c"));

		$method_call_c_m = new MethodCall($var_expr, "m");
		$resolved_to = $this->resolver->resolve($method_call_c_m);
		$this->assertEquals($this->scope_c_m, $resolved_to);
	}

	public function testMethodCallToSameNameButDifferentHierarchy() {
		$var_expr = $this->createMock(\PhpParser\Node\Expr::class);
		$var_expr->method("getAttribute")
			->with("type", $this->anything())
			->willReturn(new Type(Type::TYPE_OBJECT, array(), "e"));

		$method_call_e_m = new MethodCall($var_expr, "m");
		$resolved_to = $this->resolver->resolve($method_call_e_m);
		$this->assertEquals($this->scope_e_m, $resolved_to);
	}

	public function testMethodCallToMethodWithSameNameAsFunction() {
		$var_expr = $this->createMock(\PhpParser\Node\Expr::class);
		$var_expr->method("getAttribute")
			->with("type", $this->anything())
			->willReturn(new Type(Type::TYPE_OBJECT, array(), "f"));

		$method_call_f_k = new MethodCall($var_expr, "k");
		$resolved_to = $this->resolver->resolve($method_call_f_k);
		$this->assertEquals($this->scope_f_k, $resolved_to);
	}

	public function testStaticCallToMethodInSubclassOfImplementingClass() {
		$method_call_b_m = new StaticCall(new Name("b"), "m");
		$resolved_to = $this->resolver->resolve($method_call_b_m);
		$this->assertEquals($this->scope_a_m, $resolved_to);
	}

	public function testStaticCallToClassWhichOverridesAMethod() {
		$method_call_c_m = new StaticCall(new Name("c"), "m");
		$resolved_to = $this->resolver->resolve($method_call_c_m);
		$this->assertEquals($this->scope_c_m, $resolved_to);
	}
	public function testCallToNonExistingMethodThrowsException() {
		$var_expr = $this->createMock(\PhpParser\Node\Expr::class);
		$var_expr->method("getAttribute")
			->with("type", $this->anything())
			->willReturn(new Type(Type::TYPE_OBJECT, array(), "f"));

		$method_call_f_k = new MethodCall($var_expr, "g");
		$this->expectException(\LogicException::class);
		$this->expectExceptionMessage("Method f->g() could not be found in applies to set");
		$this->resolver->resolve($method_call_f_k);
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

	/**
	 * @param string $name
	 * @throws \PHPUnit_Framework_Exception
	 * @return Method
	 */
	private function buildScopeMock($name) {
		$method_mock = $this->createMock(Scope::class);
		$method_mock->method('getName')->willReturn($name);
		return $method_mock;
	}
}