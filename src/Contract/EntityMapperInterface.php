<?php

declare(strict_types = 1);

namespace TomasKarlik\EntityMapper\Contract;

use LogicException;
use Nette\Database\IRow;
use Nette\Utils\ArrayHash;
use TomasKarlik\EntityMapper\Exception\UndefinedPropertyException;


interface EntityMapperInterface
{

	/**
	 * @param string $class
	 * @param IRow $row
	 * @param bool $related hydrate related entites
	 * @return mixed
	 * @throws LogicException
	 * @throws UndefinedPropertyException
	 */
	function hydrate(string $class, IRow $row, bool $related = TRUE);


	/**
	 * @param string|object $class
	 * @param ArrayHash $array
	 * @return mixed
	 */
	function hydrateFromArray($class, ArrayHash &$array);


	/**
	 * @param object $entity
	 * @param array $ignored
	 * @return array
	 * @throws LogicException
	 */
	function extract(&$entity, array $ignored = []): array;

}
