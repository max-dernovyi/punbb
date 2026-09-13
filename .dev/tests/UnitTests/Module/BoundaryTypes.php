<?php
/**
 * The types that may cross a module boundary — a service contract's signatures
 * and what an event shows its observers: scalars, Api\Data interfaces and lists
 * of either, documented as such. Anything else is a data bag or a service
 * reached through the back door.
 *
 * @copyright (C) 2008-2012 PunBB, partially based on code (C) 2008-2009 FluxBB.org
 * @license http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 * @package PunBB
 */

final class BoundaryTypes {
	private const SCALARS = array('int', 'float', 'string', 'bool', 'true', 'false', 'null');

	/** @return list<string> */
	public static function parameterProblems(ReflectionClass $class, ReflectionMethod $method, ReflectionParameter $parameter): array {
		$name = '$'.$parameter->getName();
		$documented = preg_match('/@param\s+(\S+)\s+'.preg_quote($name, '/').'\b/', (string) $method->getDocComment(), $match) === 1 ? $match[1] : '';

		return self::problems($class, $parameter->getType(), $class->getShortName().'::'.$method->getName().'() '.$name, $documented, false);
	}

	/** @return list<string> */
	public static function returnProblems(ReflectionClass $class, ReflectionMethod $method): array {
		$documented = preg_match('/@return\s+(\S+)/', (string) $method->getDocComment(), $match) === 1 ? $match[1] : '';

		return self::problems($class, $method->getReturnType(), $class->getShortName().'::'.$method->getName().'() return', $documented, true);
	}

	/** @return list<string> */
	public static function propertyProblems(ReflectionClass $class, ReflectionProperty $property): array {
		$documented = preg_match('/@var\s+(\S+)/', (string) $property->getDocComment(), $match) === 1 ? $match[1] : '';

		return self::problems($class, $property->getType(), $class->getShortName().'::$'.$property->getName(), $documented, false);
	}

	/** @return list<string> */
	private static function problems(ReflectionClass $class, ?ReflectionType $type, string $where, string $documented, bool $return): array {
		if ($type === null)
			return array($where.' is untyped');

		if (!$type instanceof ReflectionNamedType && !$type instanceof ReflectionUnionType)
			return array($where.' is an intersection type');

		$problems = array();
		foreach ($type instanceof ReflectionUnionType ? $type->getTypes() : array($type) as $member)
		{
			$name = $member instanceof ReflectionNamedType ? $member->getName() : (string) $member;

			if (in_array($name, self::SCALARS, true) || in_array($name, array('self', 'static'), true) || ($name === 'void' && $return))
				continue;

			if ($name === 'array')
			{
				if (!self::documentsAList($class, $documented))
					$problems[] = $where.' is an array not documented as a list of scalars or data interfaces';
			}
			else if (!interface_exists($name) || !str_contains($name, '\\Api\\Data\\'))
				$problems[] = $where.' is '.$name.', not a scalar or an Api\\Data interface';
		}

		return $problems;
	}

	private static function documentsAList(ReflectionClass $class, string $documented): bool {
		if (preg_match('/^list<([\w\\\\]+)>(?:\|null)?$/', $documented, $list) !== 1)
			return false;

		if (in_array($list[1], array('int', 'float', 'string', 'bool'), true))
			return true;

		$element = self::resolve($class, $list[1]);

		return interface_exists($element) && str_contains($element, '\\Api\\Data\\');
	}

	/** A docblock class name, resolved the way PHP resolves it in $class's file. */
	private static function resolve(ReflectionClass $class, string $name): string {
		if (str_starts_with($name, '\\'))
			return substr($name, 1);

		$first = explode('\\', $name)[0];
		$imports = self::imports((string) $class->getFileName(), $class->getNamespaceName());

		if (isset($imports[strtolower($first)]))
			return $imports[strtolower($first)].substr($name, strlen($first));

		return $class->getNamespaceName().'\\'.$name;
	}

	/**
	 * The class imports of namespace $namespace in $file.
	 *
	 * @return array<string, string> lower-cased alias => fully-qualified name
	 */
	private static function imports(string $file, string $namespace): array {
		$tokens = array_values(array_filter(token_get_all((string) file_get_contents($file)), static fn ($token): bool =>
			!is_array($token) || !in_array($token[0], array(T_WHITESPACE, T_COMMENT, T_DOC_COMMENT), true)));

		$imports = array();
		$current = '';
		$depth = 0;
		foreach ($tokens as $i => $token)
		{
			if ($token === '{' || (is_array($token) && in_array($token[0], array(T_CURLY_OPEN, T_DOLLAR_OPEN_CURLY_BRACES), true)))
				$depth++;
			else if ($token === '}')
				$depth--;
			else if (is_array($token) && $token[0] === T_NAMESPACE && is_array($tokens[$i + 1]))
			{
				$current = $tokens[$i + 1][1];
				$depth = 0;
			}
			else if (is_array($token) && $token[0] === T_USE && $current === $namespace && $depth <= 1 && is_array($tokens[$i + 1]) && in_array($tokens[$i + 1][0], array(T_STRING, T_NAME_QUALIFIED), true))
			{
				$imported = $tokens[$i + 1][1];
				$alias = is_array($tokens[$i + 2]) && $tokens[$i + 2][0] === T_AS ? $tokens[$i + 3][1] : substr((string) strrchr('\\'.$imported, '\\'), 1);
				$imports[strtolower($alias)] = $imported;
			}
		}

		return $imports;
	}
}
