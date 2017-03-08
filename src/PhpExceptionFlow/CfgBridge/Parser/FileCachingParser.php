<?php
/**
 * This implementation is taken from https://github.com/mwijngaard/php-pdg/
 */
namespace PhpExceptionFlow\CfgBridge\Parser;

class FileCachingParser implements FileParserInterface {
	private $cache_dir;
	/** @var FileParserInterface */
	private $wrapped_parser;

	/**
	 * FileCachingParser constructor.
	 * @param string $cache_dir
	 * @param FileParserInterface $wrapped_parser
	 * @param boolean $auto_create
	 */
	public function __construct($cache_dir, FileParserInterface $wrapped_parser, $auto_create = false) {
		$this->cache_dir = $cache_dir;
		$this->wrapped_parser = $wrapped_parser;
		if ($auto_create === true && is_dir($this->cache_dir) === false) {
			mkdir($this->cache_dir, 0777, true);
		}
	}

	public function parse($filename) {
		$mtime = null;
		if (is_file($filename) === true) {
			$mtime = filemtime($filename);
		}
		$cache_file = $this->cache_dir . '/' . md5($filename) . '.cache';
		if (is_file($cache_file) === true) {
			list($script, $cached_mtime) = unserialize(file_get_contents($cache_file));
			if ($mtime === $cached_mtime) {
				return $script;
			}
		}
		$script = $this->wrapped_parser->parse($filename);
		file_put_contents($cache_file, serialize([$script, $mtime]));
		return $script;
	}
}