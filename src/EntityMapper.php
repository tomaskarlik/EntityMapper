<?php

declare(strict_types = 1);

namespace TomasKarlik\EntityMapper;

use InvalidArgumentException;
use LogicException;
use Nette\Caching\Cache;
use Nette\Caching\IStorage;
use Nette\Database\IRow;
use Nette\Database\Table\ActiveRow;
use Nette\MemberAccessException;
use Nette\Reflection\ClassType;
use Nette\Utils\ArrayHash;
use TomasKarlik\EntityMapper\Contract\EntityMapperInterface;
use TomasKarlik\EntityMapper\Exception\UndefinedPropertyException;


final class EntityMapper implements EntityMapperInterface
{

	const CACHE_NAMESPACE = 'Cache.Entity';

	/**
	 * @var Cache
	 */
	private $cache;


	public function __construct(IStorage $cacheStorage)
	{
		$this->cache = new Cache($cacheStorage, self::CACHE_NAMESPACE);
	}


	/**
	 * {@inheritdoc}
	 */
	public function hydrate(string $class, IRow $row, bool $related = TRUE)
	{
		$entity = new $class;
		$properties = $this->getEntityProperties($entity);

		try {
			foreach ($properties as $property => $column) {
				$method = $this->getMethodName($property, 'set');

				if (is_array($column)) {
					if ( ! $row instanceof ActiveRow) {
						throw new LogicException('Row must be instance of ActiveRow!');
					}

					if ($column[0] === 'ref') {
						$refRow = $row->ref($column[1], $column[2]);
						$refEntity = NULL;
						if ($refRow) {
							$refEntity = $this->hydrate($column[3], $refRow);
						}
						call_user_func([$entity, $method], $refEntity);

					} elseif ($column[0] === 'rel') {
						$relEntites = [];
						if ($related) {
							$related = $row->related($column[1], $column[2]);
							$relRows = $column[4] ? $related->order($column[4])->fetchAll() : $related->fetchAll();
							foreach ($relRows as $relRow) {
								$relEntites[] = $this->hydrate($column[3], $relRow, $related);
							}
						}
						call_user_func([$entity, $method], $relEntites);

					} else {
						throw new LogicException(sprintf('Invalid type "%s"!', $column[0]));
					}

				} else {
					$value = $row->offsetGet($column);
					call_user_func([$entity, $method], $value);
				}
			}

		} catch (MemberAccessException $exception) {
			throw new UndefinedPropertyException($exception->getMessage(), 0, $exception);
		}

		return $entity;
	}


	/**
	 * {@inheritdoc}
	 */
	public function hydrateFromArray($class, ArrayHash &$array)
	{
		$entity = is_object($class) ? $class : new $class;
		$properties = $this->getEntityProperties($entity);

		foreach ($array as $key => $value) {
			if (($property = array_search($key, $properties)) === FALSE) {
				continue;
			}
			$method = $this->getMethodName($property, 'set');
			call_user_func([$entity, $method], $value);
		}

		return $entity;
	}


	/**
	 * {@inheritdoc}
	 */
	public function extract(&$entity, array $ignored = []): array
	{
		$values = [];
		$properties = $this->getEntityProperties($entity);

		if (count($ignored)) {
			$properties = array_diff_key($properties, array_flip($ignored));
		}

		foreach ($properties as $property => $column) {
			if (is_array($column)) {
				continue; //ref. and rel. rows ignore
			}

			$method = $this->getMethodName($property, 'get');
			if ( ! method_exists($entity, $method)) {
				$method = $this->getMethodName($property, 'is');
			}

			if ( ! method_exists($entity, $method)) {
				throw new LogicException(sprintf('No get/is method for property "%s".', $property));
			}

			$values[$column] = call_user_func([$entity, $method]);
		}

		return $values;
	}


	/**
	 * @param mixed $entity
	 * @return array property name => DB row name or ref.
	 * @throws InvalidArgumentException
	 */
	private function getEntityProperties(&$entity)
	{
		$reflection = new ClassType($entity);
		$class = $reflection->getName();

		return $this->cache->load($class, function (&$dependencies) use ($reflection) {
			$entityProperties = [];
			$properties = $reflection->getProperties();

			foreach ($properties as $property) {
				$key = $property->getName();

				if ($property->isStatic()) {
					continue;

				} elseif ($property->hasAnnotation('ref') && $property->hasAnnotation('var')) { //has one
					$ref = $property->getAnnotation('ref');
					$var = $property->getAnnotation('var');

					if ( ! isset($ref[0]) || ! isset($ref[1]) || ! class_exists($var)) {
						throw new InvalidArgumentException;
					}
					$entityProperties[$key] = ['ref', $ref[0], $ref[1], $var];

				} elseif ($property->hasAnnotation('related') && $property->hasAnnotation('var')) { //has many
					$rel = $property->getAnnotation('related');
					$var = rtrim($property->getAnnotation('var'), '[]');
					$order = $property->hasAnnotation('order') ? $property->getAnnotation('order') : NULL;

					if ( ! isset($rel[0]) || ! isset($rel[1]) || ! class_exists($var)) {
						throw new InvalidArgumentException;
					}

					$entityProperties[$key] = ['rel', $rel[0], $rel[1], $var, $order];

				} else {
					if ($property->hasAnnotation('column')) {
						$column = $property->getAnnotation('column'); //custom column name

					} else {
						$column = $this->uncamelize($key);
					}

					$entityProperties[$key] = $column;
				}
			}

			return $entityProperties;
		});
	}


	/**
	 * @param string $property
	 * @param string $prefix
	 * @return string
	 */
	private function getMethodName($property, $prefix = 'get')
	{
		return $prefix . ucfirst($property);
	}


	/**
	 * @param string $string
	 * @param string $splitter
	 * @return string
	 */
	private function uncamelize($string, $splitter = '_')
	{
		$string = preg_replace(
			'/(?!^)[[:upper:]][[:lower:]]/',
			'$0',
			preg_replace('/(?!^)[[:upper:]]+/', $splitter . '$0', $string)
		);

		return strtolower($string);
	}

}
