<?php

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
	 * @return object
	 * @throws LogicException
	 * @throws UndefinedPropertyException
	 */
	function hydrate($class, IRow $row, $related = TRUE);


	/**
	 * @param string|object $class
	 * @param ArrayHash $array
	 * @return object
	 */
	function hydrateFromArray($class, ArrayHash &$array);


	/**
	 * @param object $entity
	 * @param array $ignored
	 * @return array
	 * @throws LogicException
	 */
	function extract(&$entity, array $ignored = []);

}
