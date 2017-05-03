<?php
namespace PhpExceptionFlow\CallGraphConstruction;

use PhpExceptionFlow\Scope\Scope;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\PropertyFetch;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Name;
use PHPTypes\State;
use PHPTypes\Type;

class AstCallNodeToScopeResolverTest extends \PHPUnit_Framework_TestCase {

	private $method_a_m;
	private $method_c_m;
	private $method_e_m;
	private $method_f_k;
	private $method_i_m;
	private $method_i_n;
	private $method_abs_m;
	private $method_abs_n;
	private $method_conc_m;
	private $method_conc_n;
	private $scope_a_m;
	private $scope_c_m;
	private $scope_e_m;
	private $scope_f_k;
	private $scope_fn_m;
	private $scope_fn_k;
	private $scope_i_m;
	private $scope_i_n;
	private $scope_abs_m;
	private $scope_abs_n;
	private $scope_conc_m;
	private $scope_conc_n;


	private $class_method_to_actual_implementation;
	private $function_scopes;
	private $method_scopes;

	/** @var AstCallNodeToScopeResolver $resolver */
	private $resolver;

	/**
	 * Class hierarchy used:
	 *          a[m]           e[m]            i[m, n]
	 *        /  \             |                |
	 *        b   c[m]         f[k]            abs [m, impl[n]]
	 *            |                             |
	 *            d                             conc [m, n]
	 *
	 * on top of that, functions k and m are defined.
	 */
	public function setUp() {
		$this->method_a_m = $this->buildMethodMock("a", "m");
		$this->method_c_m = $this->buildMethodMock("c", "m");
		$this->method_e_m = $this->buildMethodMock("e", "m");
		$this->method_f_k = $this->buildMethodMock("f", "k");

		$this->method_i_m = $this->buildMethodMock("i", "m", false);
		$this->method_i_n = $this->buildMethodMock("i", "n", false);
		$this->method_abs_m = $this->buildMethodMock("abs", "m", false);
		$this->method_abs_n = $this->buildMethodMock("abs", "n", true);
		$this->method_conc_m = $this->buildMethodMock("conc", "m", true);
		$this->method_conc_n = $this->buildMethodMock("conc", "n", true);

		$this->scope_a_m = $this->buildScopeMock("a::m");
		$this->scope_c_m = $this->buildScopeMock("c::m");
		$this->scope_e_m = $this->buildScopeMock("e::m");
		$this->scope_f_k = $this->buildScopeMock("f::k");
		$this->scope_i_m = $this->buildScopeMock("i::m");
		$this->scope_i_n = $this->buildScopeMock("i::n");
		$this->scope_abs_m = $this->buildScopeMock("abs::m");
		$this->scope_abs_n = $this->buildScopeMock("abs::n");
		$this->scope_conc_m = $this->buildScopeMock("conc::m");
		$this->scope_conc_n = $this->buildScopeMock("conc::n");
		$this->scope_fn_k = $this->buildScopeMock("k");
		$this->scope_fn_m = $this->buildScopeMock("m");

		$this->class_method_to_actual_implementation = [
			"a" => [
				"m" => [$this->method_a_m],
			],
			"b" => [
				"m" => [$this->method_a_m],
			],
			"c" => [
				"m" => [$this->method_c_m],
			],
			"d" => [
				"m" => [$this->method_c_m],
			],
			"e" => [
				"m" => [$this->method_e_m],
			],
			"f" => [
				"m" => [$this->method_e_m],
				"k" => [$this->method_f_k],
			],
			"i" => [
				"m" => [$this->method_conc_m],
				"n" => [$this->method_abs_n, $this->method_conc_n],
			],
			"abs" => [
				"m" => [$this->method_conc_m],
				"n" => [$this->method_abs_n, $this->method_conc_n],
			],
			"conc" => [
				"m" => [$this->method_conc_m],
				"n" => [$this->method_conc_n],
			],
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
			],

			"i" => [
				"m" => $this->scope_i_m,
				"n" => $this->scope_i_n,
			],
			"abs" => [
				"m" => $this->scope_abs_m,
				"n" => $this->scope_abs_n,
			],
			"conc" => [
				"m" => $this->scope_conc_m,
				"n" => $this->scope_conc_n,
			],
		);
		$this->resolver = new AstCallNodeToScopeResolver($this->method_scopes, $this->function_scopes, $this->class_method_to_actual_implementation, new State([]));
	}

	public function testThrowsLogicExceptionOnWrongNodeType() {
		$not_a_call = $this->createMock(PropertyFetch::class);
		$this->expectException(\LogicException::class);
		$this->resolver->resolve($not_a_call);
	}

	public function testFunctionCallGetsResolvedToFunction() {
		$func_call_m = new FuncCall(new Name("m"));
		$resolved_to = $this->resolver->resolve($func_call_m);
		$this->assertEquals([$this->scope_fn_m], $resolved_to);
	}

	public function testMethodCallToMethodInImplementingClass() {
		$var_expr = $this->createMock(\PhpParser\Node\Expr::class);
		$var_expr->method("getAttribute")
			->with("type", $this->anything())
			->willReturn(new Type(Type::TYPE_OBJECT, array(), "a"));

		$method_call_a_m = new MethodCall($var_expr, "m");
		$resolved_to = $this->resolver->resolve($method_call_a_m);
		$this->assertEquals([$this->scope_a_m], $resolved_to);
	}

	public function testMethodCallToMethodInSubclassOfImplementingClass() {
		$var_expr = $this->createMock(\PhpParser\Node\Expr::class);
		$var_expr->method("getAttribute")
			->with("type", $this->anything())
			->willReturn(new Type(Type::TYPE_OBJECT, array(), "b"));

		$method_call_b_m = new MethodCall($var_expr, "m");
		$resolved_to = $this->resolver->resolve($method_call_b_m);
		$this->assertEquals([$this->scope_a_m], $resolved_to);
	}

	public function testMethodCallToClassWhichOverridesAMethod() {
		$var_expr = $this->createMock(\PhpParser\Node\Expr::class);
		$var_expr->method("getAttribute")
			->with("type", $this->anything())
			->willReturn(new Type(Type::TYPE_OBJECT, array(), "c"));

		$method_call_c_m = new MethodCall($var_expr, "m");
		$resolved_to = $this->resolver->resolve($method_call_c_m);
		$this->assertEquals([$this->scope_c_m], $resolved_to);
	}

	public function testMethodCallToSameNameButDifferentHierarchy() {
		$var_expr = $this->createMock(\PhpParser\Node\Expr::class);
		$var_expr->method("getAttribute")
			->with("type", $this->anything())
			->willReturn(new Type(Type::TYPE_OBJECT, array(), "e"));

		$method_call_e_m = new MethodCall($var_expr, "m");
		$resolved_to = $this->resolver->resolve($method_call_e_m);
		$this->assertEquals([$this->scope_e_m], $resolved_to);
	}

	public function testMethodCallToMethodWithSameNameAsFunction() {
		$var_expr = $this->createMock(\PhpParser\Node\Expr::class);
		$var_expr->method("getAttribute")
			->with("type", $this->anything())
			->willReturn(new Type(Type::TYPE_OBJECT, array(), "f"));

		$method_call_f_k = new MethodCall($var_expr, "k");
		$resolved_to = $this->resolver->resolve($method_call_f_k);
		$this->assertEquals([$this->scope_f_k], $resolved_to);
	}

	public function testStaticCallToMethodInSubclassOfImplementingClass() {
		$method_call_b_m = new StaticCall(new Name("b"), "m");
		$resolved_to = $this->resolver->resolve($method_call_b_m);
		$this->assertEquals([$this->scope_a_m], $resolved_to);
	}

	public function testStaticCallToClassWhichOverridesAMethod() {
		$method_call_c_m = new StaticCall(new Name("c"), "m");
		$resolved_to = $this->resolver->resolve($method_call_c_m);
		$this->assertEquals([$this->scope_c_m], $resolved_to);
	}
	public function testCallToNonExistingMethodThrowsException() {
		$var_expr = $this->createMock(\PhpParser\Node\Expr::class);
		$var_expr->method("getAttribute")
			->with("type", $this->anything())
			->willReturn(new Type(Type::TYPE_OBJECT, array(), "f"));

		$method_call_f_k = new MethodCall($var_expr, "g");
		$this->expectException(\UnexpectedValueException::class);
		$this->expectExceptionMessage("Method f->g() could not be found in applies to set");
		$this->resolver->resolve($method_call_f_k);
	}

	public function testCallToInterfaceMethodResolvesToAllConcreteImplementingMethods() {
		$var_expr = $this->createMock(\PhpParser\Node\Expr::class);
		$var_expr->method("getAttribute")
			->with("type", $this->anything())
			->willReturn(new Type(Type::TYPE_OBJECT, array(), "i")); //i is an interface

		$method_call_i_m = new MethodCall($var_expr, "n");
		$resolved_to = $this->resolver->resolve($method_call_i_m);
		$this->assertEquals([$this->scope_abs_n, $this->scope_conc_n], $resolved_to);
	}

	public function testCallToAbstractMethodResolvesToAllConcreteImplementingMethods() {
		$var_expr = $this->createMock(\PhpParser\Node\Expr::class);
		$var_expr->method("getAttribute")
			->with("type", $this->anything())
			->willReturn(new Type(Type::TYPE_OBJECT, array(), "abs")); //abs is an abstract class

		$method_call_i_m = new MethodCall($var_expr, "m"); //m is an abstract method
		$resolved_to = $this->resolver->resolve($method_call_i_m);
		$this->assertEquals([$this->scope_conc_m], $resolved_to);
	}

	public function testCallToInterfaceMethodWithBothAbstractAndConcreteMethodResolvesToConcrete() {
		$var_expr = $this->createMock(\PhpParser\Node\Expr::class);
		$var_expr->method("getAttribute")
			->with("type", $this->anything())
			->willReturn(new Type(Type::TYPE_OBJECT, array(), "i")); //i is an interface

		$method_call_i_m = new MethodCall($var_expr, "m"); //i->m: interface, abs->m: abstract, so should resolve to conc->m
		$resolved_to = $this->resolver->resolve($method_call_i_m);
		$this->assertEquals([$this->scope_conc_m], $resolved_to);
	}

	public function testParentCallResolvedCorrectly() {
		$this->markTestIncomplete("Still todo");
	}


	/**
	 * @param string $class
	 * @param string $method_name
	 * @throws \PHPUnit_Framework_Exception
	 * @return Method
	 */
	private function buildMethodMock($class, $method_name, $implemented = true) {
		$method_mock = $this->createMock(Method::class);
		$method_mock->method('getClass')->willReturn($class);
		$method_mock->method('getName')->willReturn($method_name);
		$method_mock->method('isImplemented')->willReturn($implemented);
		return $method_mock;
	}

	/**
	 * @param string $name
	 * @throws \PHPUnit_Framework_Exception
	 * @return Method
	 */
	private function buildScopeMock($name) {
		$scope_mock = $this->createMock(Scope::class);
		$scope_mock->method('getName')->willReturn($name);
		return $scope_mock;
	}
}