<?php
require __DIR__ . '/../vendor/autoload.php';

if ($argc !== 3) {
	throw new \UnexpectedValueException(sprintf("Expected exactly 2 input arguments, got %d", $argc - 1));
}

$project_path = $argv[1];
$path_to_output_folder = $argv[2];

$runner = new \PhpExceptionFlow\Runner($project_path, $path_to_output_folder);
$runner->run();