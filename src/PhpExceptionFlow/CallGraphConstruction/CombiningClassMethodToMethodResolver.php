<?php
namespace PhpExceptionFlow\CallGraphConstruction;


use PhpExceptionFlow\Collection\PartialOrderInterface;

class CombiningClassMethodToMethodResolver implements MethodCallToMethodResolverInterface {
	/** @var MethodCallToMethodResolverInterface[] */
	private $resolvers = [];

	public function addResolver(MethodCallToMethodResolverInterface $resolver) {
		$this->resolvers[] = $resolver;
	}

	public function fromPartialOrder(PartialOrderInterface $partial_order) {
		$class_method_map = [];
		foreach ($this->resolvers as $resolver) {
			$partial_map = $resolver->fromPartialOrder($partial_order);
			foreach ($partial_map as $class => $call_sites) {
				foreach ($call_sites as $call_site_name => $methods) {
					if (isset($class_method_map[$class]) === false) {
						$class_method_map[$class] = [
							$call_site_name => $methods
						];
					} else if (isset($class_method_map[$class][$call_site_name]) === false) {
						$class_method_map[$class][$call_site_name] = $methods;
					} else {
						foreach ($methods as $method) {
							if (in_array($method, $class_method_map[$class][$call_site_name], true) === false) {
								$class_method_map[$class][$call_site_name][] = $method;
							}
						}
					}
				}
			}
		}
		return $class_method_map;
	}
}