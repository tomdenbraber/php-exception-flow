<?php
namespace PhpExceptionFlow\Scope;

use PhpParser\Node;

class Scope {
	/** @var string $name */
	private $name;
	/** @var Node[] $instructions */
	private $instructions;
	/** @var GuardedScope[] $guarded_scopes */
	private $guarded_scopes;
	/** @var GuardedScope $enclosing_guarded_scope */
	private $enclosing_guarded_scope;

	/**
	 * Scope constructor.
	 * @param string $name
	 * @param GuardedScope $enclosing_guarded_scope
	 * @param Node[] $instructions
	 * @param GuardedScope[] $guarded_scopes
	 */
	public function __construct($name, GuardedScope $enclosing_guarded_scope = null, $instructions = array(), $guarded_scopes = array()) {
		$this->name = $name;
		$this->instructions = array();
		$this->guarded_scopes = array();

		$this->enclosing_guarded_scope = $enclosing_guarded_scope;

		foreach ($instructions as $instruction) {
			$this->addInstruction($instruction);
		}

		foreach ($guarded_scopes as $guarded_scope) {
			$this->addGuardedScope($guarded_scope);
		}
	}

	/**
	 * @param Node $stmt
	 */
	public function addInstruction(Node $stmt) {
		$this->instructions[] = $stmt;
	}

	/**
	 * @param GuardedScope $guarded_scope
	 */
	public function addGuardedScope(GuardedScope $guarded_scope) {
		$this->guarded_scopes[] = $guarded_scope;
	}

	/**
	 * @param GuardedScope $guarded_scope
	 */
	public function setEnclosingGuardedScope(GuardedScope $guarded_scope) {
		$this->enclosing_guarded_scope = $guarded_scope;
	}

	/**
	 * @return string
	 */
	public function getName() {
		return $this->name;
	}

	/**
	 * @return Node[]
	 */
	public function getInstructions() {
		return $this->instructions;
	}

	/**
	 * @return GuardedScope[]
	 */
	public function getGuardedScopes() {
		return $this->guarded_scopes;
	}

	/**
	 * @return GuardedScope
	 */
	public function getEnclosingGuardedScope() {
		return $this->enclosing_guarded_scope;
	}

	/**
	 * @return bool
	 */
	public function isEnclosed() {
		return $this->enclosing_guarded_scope !== null;
	}
}