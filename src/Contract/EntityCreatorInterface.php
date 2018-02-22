<?php

declare(strict_types = 1);

namespace TomasKarlik\EntityMapper\Contract;

use TomasKarlik\EntityMapper\Exception\EntityCreatorException;


interface EntityCreatorInterface
{

	/**
	 * @param string $table
	 * @param int $chmod
	 * @throws EntityCreatorException
	 */
	function create(string $table, int $chmod = 0755): void;

}
