<?php
/**
 * This implementation is taken from https://github.com/mwijngaard/php-pdg/
 */
namespace PhpExceptionFlow\CfgBridge;

use PHPCfg\Script;

class System {
	/** @var  Script[] */
	private $scripts = [];

	public function addScript($filename, Script $script) {
		if (isset($this->scripts[$filename]) === true) {
			throw new \InvalidArgumentException("CFG with filename `$filename` already exists");
		}
		$this->scripts[$filename] = $script;
	}

	/**
	 * @return string[]
	 */
	public function getFilenames() {
		return array_keys($this->scripts);
	}

	/**
	 * @param string $filename
	 * @return Script
	 * @throws \InvalidArgumentException
	 */
	public function getScript($filename) {
		if (isset($this->scripts[$filename]) === false) {
			throw new \InvalidArgumentException("No CFG with filename `$filename`");
		}
		return $this->scripts[$filename];
	}
}