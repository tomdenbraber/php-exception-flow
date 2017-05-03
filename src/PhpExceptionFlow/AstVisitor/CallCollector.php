<?php
namespace PhpExceptionFlow\AstVisitor;

use PhpParser\Node;
use PhpParser\NodeVisitorAbstract;

class CallCollector extends NodeVisitorAbstract {
	/** @var \SplObjectStorage $func_calls */
	private $func_calls;
	/** @var \SplObjectStorage $method_calls */
	private $method_calls;
	/** @var \SplObjectStorage $static_calls */
	private $static_calls;
	/** @var \SplObjectStorage $constructor_calls */
	private $constructor_calls;

	public function __construct() {
		$this->func_calls = new \SplObjectStorage;
		$this->method_calls = new \SplObjectStorage;
		$this->static_calls = new \SplObjectStorage;
		$this->constructor_calls = new \SplObjectStorage;
	}

	public function enterNode(Node $node) {
		if ($node instanceof Node\Expr\FuncCall) {
			$this->func_calls->attach($node);
		} else if ($node instanceof Node\Expr\MethodCall) {
			$this->method_calls->attach($node);
		} else if ($node instanceof Node\Expr\StaticCall) {
			$this->static_calls->attach($node);
		} else if ($node instanceof Node\Expr\New_) {
			$this->constructor_calls->attach($node);
		}
	}

	/**
	 * @return \SplObjectStorage
	 */
	public function getFunctionCalls() {
		return $this->func_calls;
	}

	/**
	 * @return \SplObjectStorage
	 */
	public function getMethodCalls() {
		return $this->method_calls;
	}

	/**
	 * @return \SplObjectStorage
	 */
	public function getStaticCalls() {
		return $this->static_calls;
	}

	/**
	 * @return \SplObjectStorage
	 */
	public function getConstructorCalls() {
		return $this->constructor_calls;
	}

	/**
	 * empties all the sets of collected calls
	 */
	public function reset() {
		$this->func_calls = new \SplObjectStorage;
		$this->method_calls = new \SplObjectStorage;
		$this->static_calls = new \SplObjectStorage;
		$this->constructor_calls = new \SplObjectStorage;
	}
}