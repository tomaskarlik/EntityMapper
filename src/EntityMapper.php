<?php

declare(strict_types = 1);

namespace TomasKarlik\EntityMapper;

use InvalidArgumentException;
use LogicException;
use Nette\Caching\Cache;
use Nette\Caching\IStorage;
use Nette\Database\IRow;
use Nette\Database\IStructure;
use Nette\Database\Table\ActiveRow;
use Nette\MemberAccessException;
use Nette\Reflection\ClassType;
use Nette\Utils\ArrayHash;
use TomasKarlik\EntityMapper\Contract\EntityMapperInterface;
use TomasKarlik\EntityMapper\DI\Configuration;
use TomasKarlik\EntityMapper\Exception\MissingConfigurationException;
use TomasKarlik\EntityMapper\Exception\UndefinedPropertyException;


final class EntityMapper implements EntityMapperInterface
{

	const CACHE_NAMESPACE = 'Cache.Entity';

	/**
	 * @var Cache
	 */
	private $cache;

	/**
	 * @var Configuration
	 */
	private $configuration;


	public function __construct(
		Configuration $configuration,
		IStorage $cacheStorage
	) {
		$this->cache = new Cache($cacheStorage, self::CACHE_NAMESPACE);
		$this->configuration = $configuration;
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

				if ($column[0] !== 'column') { //@TODO hotfix PHP7
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
							$relatedRow = $row->related($column[1], $column[2]);
							$primaryKey = $relatedRow->getPrimary(FALSE);
							$primaryKey = $primaryKey && is_scalar($primaryKey) ? $primaryKey : NULL;
							$relRows = $column[4] ? $relatedRow->order($column[4])->fetchAll() : $relatedRow->fetchAll();
							foreach ($relRows as $relRow) {
								if ($primaryKey !== NULL && isset($relRow[$primaryKey])) {
									$relEntites[$relRow[$primaryKey]] = $this->hydrate($column[3], $relRow, $related);

								} else {
									$relEntites[] = $this->hydrate($column[3], $relRow, $related);
								}
							}
						}
						call_user_func([$entity, $method], $relEntites);

					} else {
						throw new LogicException(sprintf('Invalid type "%s"!', $column[0]));
					}

				} else {
					$value = $row->offsetGet($column[1]);
					$this->setType($value, $column[2]); //@TODO hotfix PHP7
					if ($column[3]) {
						$value = $this->decrypt($value);
					}
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
	public function hydrateFromArray($class, ArrayHash &$array, bool $encrypt = TRUE)
	{
		$entity = is_object($class) ? $class : new $class;
		$properties = $this->getEntityProperties($entity);

		$properties = array_filter($properties, function ($column) { //@TODO hotfix PHP7
			return isset($column[0]) && $column[0] === 'column';
		});
		$columns = array_combine( //@TODO hotfix PHP7
			array_keys($properties),
			array_column($properties, 1)
		);

		foreach ($array as $key => $value) {
			if (($property = array_search($key, $columns)) === FALSE) {
				continue;
			}
			if ($encrypt && $properties[$property][3]) {
				$value = $this->encrypt($value);
			}
			$method = $this->getMethodName($property, 'set');
			$this->setType($value, $properties[$property][2]); //@TODO hotfix PHP7
			call_user_func([$entity, $method], $value);
		}

		return $entity;
	}


	/**
	 * {@inheritdoc}
	 */
	public function extract(&$entity, array $ignored = [], bool $encrypt = TRUE): array
	{
		$values = [];
		$properties = $this->getEntityProperties($entity);

		if (count($ignored)) {
			$properties = array_diff_key($properties, array_flip($ignored));
		}

		foreach ($properties as $property => $column) {
			if ($column[0] !== 'column') { //@TODO hotfix PHP7
				continue; //ref. and rel. rows ignore
			}

			$method = $this->getMethodName($property, 'get');
			if ( ! method_exists($entity, $method)) {
				$method = $this->getMethodName($property, 'is');
			}

			if ( ! method_exists($entity, $method)) {
				throw new LogicException(sprintf('No get/is method for property "%s".', $property));
			}

			if ($encrypt && $column[3]) {
				$values[$column[1]] = $this->encrypt(call_user_func([$entity, $method]));

			} else {
				$values[$column[1]] = call_user_func([$entity, $method]); //@TODO hotfix PHP7
			}
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
					$var = $this->getVariableType($property->getAnnotation('var'));

					if ( ! isset($ref[0]) || ! isset($ref[1]) || ! class_exists($var)) {
						throw new InvalidArgumentException;
					}
					$entityProperties[$key] = ['ref', $ref[0], $ref[1], $var];

				} elseif ($property->hasAnnotation('related') && $property->hasAnnotation('var')) { //has many
					$rel = $property->getAnnotation('related');
					$var = $this->getVariableType($property->getAnnotation('var'));
					$order = $property->hasAnnotation('order') ? $property->getAnnotation('order') : NULL;

					if ( ! isset($rel[0]) || ! isset($rel[1]) || ! class_exists($var)) {
						throw new InvalidArgumentException;
					}

					$entityProperties[$key] = ['rel', $rel[0], $rel[1], $var, $order];

				} elseif ($property->hasAnnotation('var')) {
					if ($property->hasAnnotation('column')) {
						$column = $property->getAnnotation('column'); //custom column name

					} else {
						$column = $this->uncamelize($key);
					}

					$var = $this->getVariableType($property->getAnnotation('var'));
					$encrypted = $property->hasAnnotation('encrypted');
					$entityProperties[$key] = ['column', $column, $var, $encrypted];

				} else { // missing annotation
					throw new InvalidArgumentException(sprintf('Property "%s" annotation error!', $property));
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


	/**
	 * @deprecated PHP7 hotfix
	 * @param mixed $value
	 * @param string $type
	 * @return void
	 */
	private function setType(&$value, string $type): void
	{
		$scalarTypes = [
			IStructure::FIELD_BOOL,
			IStructure::FIELD_INTEGER,
			IStructure::FIELD_FLOAT,
			IStructure::FIELD_TEXT
		];
		if ( ! in_array($type, $scalarTypes) || $value === NULL) {
			return;
		}
		if ( ! settype($value, $type)) {
			throw new InvalidArgumentException('Invalid property value!');
		}
	}


	/**
	 * @param mixed $value
	 * @return mixed
	 */
	private function decrypt($value)
	{
		if (empty($this->configuration->getPassword())) {
			throw new MissingConfigurationException('Missing password for decrypt entity values!');
		}
		return openssl_decrypt((string) $value, 'AES-128-ECB', $this->configuration->getPassword());
	}


	/**
	 * @param mixed $value
	 * @return mixed
	 */
	private function encrypt($value)
	{
		if (empty($this->configuration->getPassword())) {
			throw new MissingConfigurationException('Missing password for encrypt entity values!');
		}
		return openssl_encrypt((string) $value, 'AES-128-ECB', $this->configuration->getPassword());
	}


	/**
	 * @todo PHP7 fix
	 * @param string $value
	 * @return string
	 */
	private function getVariableType($value)
	{
		return preg_replace('#([^\|\[\]]+).*$#', '$1', trim((string) $value));
	}

}
