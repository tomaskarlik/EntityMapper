<?php

declare(strict_types = 1);

namespace TomasKarlik\EntityMapper\DI;

use Nette\DI\CompilerExtension;
use Nette\Utils\Validators;
use TomasKarlik\EntityMapper\Command\CreateEntityCommand;
use TomasKarlik\EntityMapper\EntityCreator;
use TomasKarlik\EntityMapper\EntityMapper;


final class EntityMapperExtension extends CompilerExtension
{

	/**
	 * @var array
	 */
	private $defaults = [
		'directory' => NULL,
		'namespace' => 'App\\Model\\Entity',
		'namespaces' => [],
		'password' => NULL,
		'traits' => []
	];


	public function loadConfiguration(): void
	{
		$builder = $this->getContainerBuilder();
		$configuration = $this->createConfigurationObject();

		$builder->addDefinition($this->prefix('createEntityCommand'))
			->setClass(CreateEntityCommand::class)
			->addTag('kdyby.console.command');

		$builder->addDefinition($this->prefix('entityCreator'))
			->setClass(EntityCreator::class, [$configuration]);

		$builder->addDefinition($this->prefix('entityMapper'))
			->setClass(EntityMapper::class, [$configuration]);
	}


	private function createConfigurationObject(): Configuration
	{
		$config = $this->validateConfig($this->defaults);

		Validators::assertField($config, 'directory', 'string');
		Validators::assertField($config, 'namespace', 'string');
		Validators::assertField($config, 'namespaces', 'array');
		Validators::assertField($config, 'traits', 'array');

		$configuration = new Configuration;
		$configuration->setEntitesPath(realpath($config['directory']));
		$configuration->setNamespace(trim($config['namespace'], Configuration::NAMESPACE_SEPARATOR));
		$configuration->setNamespaces($config['namespaces']);
		$configuration->setPassword($config['password']);
		$configuration->setTraits($config['traits']);

		return $configuration;
	}

}
