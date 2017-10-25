<?php

namespace TomasKarlik\EntityMapper\DI;

use Nette\DI\CompilerExtension;
use TomasKarlik\EntityMapper\EntityCreator;
use TomasKarlik\EntityMapper\EntityMapper;


final class EntityMapperExtension extends CompilerExtension
{

	public function loadConfiguration()
	{
		$builder = $this->getContainerBuilder();

		$builder->addDefinition($this->prefix('entityCreator'))
			->setClass(EntityCreator::class);

		$builder->addDefinition($this->prefix('entityMapper'))
			->setClass(EntityMapper::class);
	}

}
