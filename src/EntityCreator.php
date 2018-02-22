<?php

declare(strict_types = 1);

namespace TomasKarlik\EntityMapper;

use DateTime;
use Nette\Database\Context;
use Nette\Database\Helpers;
use Nette\Database\IStructure;
use Nette\PhpGenerator\ClassType;
use Nette\PhpGenerator\PhpLiteral;
use Nette\PhpGenerator\PhpNamespace;
use Nette\Utils\Strings;
use TomasKarlik\EntityMapper\Contract\EntityCreatorInterface;
use TomasKarlik\EntityMapper\DI\Configuration;
use TomasKarlik\EntityMapper\Exception\EntityCreatorException;


final class EntityCreator implements EntityCreatorInterface
{

	/**
	 * @var Configuration
	 */
	private $configuration;

	/**
	 * @var Context
	 */
	private $context;


	public function __construct(Configuration $configuration, Context $context)
	{
		$this->configuration = $configuration;
		$this->context = $context;
	}


	/**
	 * {@inheritdoc}
	 */
	public function create(string $table, int $chmod = 0755): void
	{
		$columns = $this->context->getStructure()->getColumns($table);

		$namespace = $this->getNamespaceFromTable($table);
		$class = $this->getClassFromTable($namespace, $table);

		$this->createMemberProperties($class, $columns);
		$this->createMemberMethods($class, $columns);
		$this->createUses($namespace, $columns);

		$file = $this->getFileName($class);
		$this->writeFile($namespace, $file, $chmod);
	}


	private function getNamespaceFromTable(string $table): PhpNamespace
	{
		$namespace = $this->configuration->findNamespace($table);
		if ( ! $namespace) {
			throw new EntityCreatorException(sprintf('Namespace for table "%s" not found in configuration!', $table));
		}

		return new PhpNamespace($namespace);
	}


	private function getClassFromTable(PhpNamespace $namespace, string $table): ClassType
	{
		$className = $this->camelize($table);
		return $namespace->addClass($className);
	}


	private function camelize(string $string, string $delimiter = '_'): string
	{
		$string = ucwords($string, $delimiter);
		return str_replace($delimiter, '', $string);
	}


	private function createMemberProperties(ClassType $class, array $columns): void
	{
		foreach ($columns as $column) {
			$propertyName = $this->camelize($column['name']);
			$propertyType = $this->getPropertyType($column['nativetype']);

			$property = $class->addProperty(Strings::firstLower($propertyName));
			$property->setVisibility('private');
			$property->addComment(sprintf(
				'@var %s%s',
				$propertyType,
				$column['nullable'] ? '|NULL' : ''
			));

			if ($column['default'] && $propertyType === IStructure::FIELD_BOOL) {
				$property->setValue(strtolower(substr($column['default'], 0, 1)) === 't');

			} elseif ($column['nullable']) {
				$property->setValue(new PhpLiteral('NULL'));
			}
		}
	}


	private function createMemberMethods(ClassType $class, array $columns): void
	{
		foreach ($columns as $column) {
			$propertyName = $this->camelize($column['name']);
			$propertyType = $this->getPropertyType($column['nativetype']);
			$getterPrefix = $propertyType === IStructure::FIELD_BOOL ? 'is' : 'get';

			// getter
			$methodGet = $class->addMethod(sprintf('%s%s', $getterPrefix, $propertyName));
			$methodGet->setVisibility('public');
			$methodGet->setReturnType($propertyType);
			$methodGet->setBody(sprintf('return $this->%s;', Strings::firstLower($propertyName)));
			$methodGet->addComment(sprintf(
				'@return %s%s',
				$propertyType,
				$column['nullable'] ? '|NULL' : ''
			));

			if ($column['nullable']) {
				$methodGet->setReturnNullable();
			}

			// setter
			$methodSet = $class->addMethod(sprintf('set%s', $propertyName));
			$methodSet->setVisibility('public');
			$methodSet->setReturnType('void');
			$methodSet->setBody(sprintf(
				'$this->%s = $%s;',
				Strings::firstLower($propertyName),
				Strings::firstLower($propertyName)
			));
			$methodSet->addComment(sprintf(
				'@param %s%s $%s',
				$propertyType,
				$column['nullable'] ? '|NULL' : '',
				Strings::firstLower($propertyName)
			));
			$parameter = $methodSet->addParameter(Strings::firstLower($propertyName));
			$parameter->setTypeHint($propertyType);

			if ($column['nullable']) {
				$parameter->setNullable();
			}
		}
	}

	private function createUses(PhpNamespace $namespace, array $columns): void
	{
		$scalarTypes = [
			IStructure::FIELD_BOOL,
			IStructure::FIELD_INTEGER,
			IStructure::FIELD_FLOAT,
			IStructure::FIELD_TEXT
		];

		$types = array_column($columns, 'nativetype');
		$types = array_map(function ($type) {
			return $this->getPropertyType($type);
		}, $types);
		$types = array_unique($types);
		$uses = array_diff($types, $scalarTypes);
		sort($uses, SORT_STRING);

		foreach ($uses as $use) {
			$namespace->addUse($use);
		}
	}


	private function getPropertyType(string $nativeType): string
	{
		$type = Helpers::detectType($nativeType);
		switch($type) {
			case IStructure::FIELD_BOOL:
			case IStructure::FIELD_INTEGER:
			case IStructure::FIELD_FLOAT:
			case IStructure::FIELD_TEXT:
				return $type; // scalar types
			case IStructure::FIELD_DATE:
			case IStructure::FIELD_DATETIME:
			case IStructure::FIELD_TIME:
				return DateTime::class;
			default:
				throw new EntityCreatorException(sprintf('Not supported type "%s"!', $type));
		}
	}


	private function getFileName(ClassType $classType, string $suffix = '.php'): string
	{
		$phpNamespace = $classType->getNamespace();
		if ( ! $phpNamespace) {
			throw new EntityCreatorException(sprintf('Entity "%s" hasn\'t NS!', $classType->getName()));
		}

		$basePattern = preg_quote($this->configuration->getNamespace(), '#');
		$namespace = trim($phpNamespace->getName(), Configuration::NAMESPACE_SEPARATOR);

		if ( ! preg_match(sprintf('#^%s#', $basePattern), $namespace)) {
			throw new EntityCreatorException(sprintf(
				'Entity "%s" NS not starts with "%s"!',
				$classType->getName(),
				$this->configuration->getNamespace()
			));
		}

		$path = preg_replace(sprintf('#^%s(.*)$#', $basePattern), '$1', $namespace);
		$path = str_replace(Configuration::NAMESPACE_SEPARATOR, DIRECTORY_SEPARATOR, $path);
		$path =  $this->configuration->getEntitesPath() . $path;

		return $path . DIRECTORY_SEPARATOR . $classType->getName() . $suffix;
	}


	private function writeFile(PhpNamespace $namespace, string $file, int $chmod = 0755, bool $overwrite = FALSE): void
	{
		if (file_exists($file) && ! $overwrite) {
			throw new EntityCreatorException(sprintf('File "%s" is exists!', $file));
		}

		$targetDirectory = dirname($file);
		if ( ! file_exists($targetDirectory)) {
			mkdir($targetDirectory, $chmod, TRUE);
		}

		$content = "<?php\n\ndeclare(strict_types = 1);\n\n" . (string) $namespace . "\n";
		if (file_put_contents($file, $content) === FALSE) {
			throw new EntityCreatorException(sprintf('File "%s" write error!', $file));
		}

		chmod($file, $chmod);
	}
}
