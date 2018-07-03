<?php

declare(strict_types = 1);

namespace TomasKarlik\EntityMapper\DI;

use Nette\Utils\Strings;


final class Configuration
{

	public const NAMESPACE_SEPARATOR = '\\';

	/**
	 * @var string
	 */
	private $entitesPath;

	/**
	 * @var string
	 */
	private $namespace;

	/**
	 * @var string[]
	 */
	private $namespaces = [];

	/**
	 * @var string|NULL
	 */
	private $password = NULL;

	/**
	 * @var string[]
	 */
	private $traits = [];


	public function getEntitesPath(): string
	{
		return $this->entitesPath;
	}


	public function setEntitesPath(string $entitesPath): void
	{
		$this->entitesPath = $entitesPath;
	}


	public function getNamespace(): string
	{
		return $this->namespace;
	}


	public function setNamespace(string $namespace): void
	{
		$this->namespace = $namespace;
	}


	public function getNamespaces(): array
	{
		return $this->namespaces;
	}


	public function findNamespace(string $table): ?string
	{
		foreach ($this->namespaces as $ns => $tables) {
			if (Strings::startsWith($ns, self::NAMESPACE_SEPARATOR)) {
				$ns = $this->namespace . $ns; // absolute path
			}
			if (in_array($table, $tables)) {
				return $ns;
			}
		}
		return NULL;
	}


	public function setNamespaces(array $namespaces): void
	{
		$this->namespaces = $namespaces;
	}


	public function getPassword(): ?string
	{
		return $this->password;
	}


	public function setPassword(?string $password): void
	{
		$this->password = $password;
	}


	public function getTraits(): array
	{
		return $this->traits;
	}


	public function setTraits(array $traits): void
	{
		$this->traits = $traits;
	}

}
