<?php

namespace TomasKarlik\EntityMapper\Contract;

use TomasKarlik\EntityMapper\Exception\EntityCreatorException;


interface EntityCreatorInterface
{

	/**
	 * @param string $table
	 * @param string $dir
	 * @throws EntityCreatorException
	 */
	function create($table, $dir);

}
