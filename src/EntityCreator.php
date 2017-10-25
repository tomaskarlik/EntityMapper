<?php

namespace TomasKarlik\EntityMapper;

use Nette\Database\Context;
use Nette\Database\Helpers;
use Nette\Utils\Strings;
use TomasKarlik\EntityMapper\Contract\EntityCreatorInterface;
use TomasKarlik\EntityMapper\Exception\EntityCreatorException;


final class EntityCreator implements EntityCreatorInterface
{

	const NEW_LINE = "\r\n";
	const IDENT = "\t";

	/**
	 * @var Context
	 */
	private $context;


	public function __construct(Context $context)
	{
		$this->context = $context;
	}


	/**
	 * {@inheritdoc}
	 */
	public function create($table, $dir)
	{
		$columns = $this->context->getStructure()->getColumns($table);
		$className = $this->camelize($table);

		$content = '<?php';
		$content .= $this->newLine(2);
		$content .= 'namespace App\Model\Entity;';
		$content .= $this->newLine(3);
		$content .= sprintf('class %s', $className);
		$content .= $this->newLine();
		$content .= '{';
		$content .= $this->newLine(2);

		$content .= $this->createMemberVariables($columns);
		$content .= $this->newLine(3);
		$content .= $this->createMemberFunctions($columns);
		$content .= $this->newLine(2);

		$content .= '}';
		$content .= $this->newLine();

		$dir = realpath($dir);
		if ($dir === FALSE) {
			throw new EntityCreatorException(sprintf('Invalid directory path "%s"!', $dir));
		}

		$file = $dir . DIRECTORY_SEPARATOR . $className . '.php';
		if (file_exists($file)) {
			throw new EntityCreatorException(sprintf('File "%s" is exists!', $file));
		}

		if (file_put_contents($file, $content) === FALSE) {
			throw new EntityCreatorException(sprintf('File "%s" write error!', $file));
		}
	}


	/**
	 * @param array $columns
	 * @return string
	 */
	private function createMemberFunctions(array $columns)
	{
		$content = '';

		foreach ($columns as $column) {
			$columnName = $this->camelize($column['name']);
			$columnType = Helpers::detectType($column['nativetype']);
			$columnNullable = (bool) $column['nullable'];

			//getter
			$prefix = $columnType === 'bool' ? 'is' : 'get';
			$content .= $this->ident('/**');
			$content .= $this->newLine();
			$content .= $this->ident(sprintf(' * @return %s%s', $columnType, $columnNullable ? '|NULL' : ''));
			$content .= $this->newLine();
			$content .= $this->ident(' */');
			$content .= $this->newLine();
			$content .= $this->ident(sprintf('public function %s%s()', $prefix, $columnName));
			$content .= $this->newLine();
			$content .= $this->ident('{');
			$content .= $this->newLine();
			$content .= $this->ident(sprintf('return $this->%s;', Strings::firstLower($columnName)), 2);
			$content .= $this->newLine();
			$content .= $this->ident('}');
			$content .= $this->newLine(3);

			//setter
			$content .= $this->ident('/**');
			$content .= $this->newLine();
			$content .= $this->ident(
				sprintf(' * @param %s%s $%s', $columnType, $columnNullable ? '|NULL' : '', Strings::firstLower($columnName))
			);
			$content .= $this->newLine();
			$content .= $this->ident(' */');
			$content .= $this->newLine();
			$content .= $this->ident(
				sprintf('public function set%s($%s)', $columnName, Strings::firstLower($columnName))
			);
			$content .= $this->newLine();
			$content .= $this->ident('{');
			$content .= $this->newLine();
			$content .= $this->ident(
				sprintf('$this->%s = $%s;', Strings::firstLower($columnName), Strings::firstLower($columnName)), 2
			);
			$content .= $this->newLine();
			$content .= $this->ident('}');
			$content .= $this->newLine(3);
		}

		return rtrim($content);
	}


	/**
	 * @param array $columns
	 * @return string
	 */
	private function createMemberVariables(array $columns)
	{
		$content = '';

		foreach ($columns as $column) {
			$columnName = $this->camelize($column['name']);
			$columnType = Helpers::detectType($column['nativetype']);
			$columnNullable = (bool) $column['nullable'];

			$content .= $this->ident('/**');
			$content .= $this->newLine();
			$content .= $this->ident(sprintf(' * @var %s%s', $columnType, $columnNullable ? '|NULL' : ''));
			$content .= $this->newLine();
			$content .= $this->ident(' */');
			$content .= $this->newLine();
			if ($columnNullable) {
				$content .= $this->ident(sprintf('private $%s = NULL;', Strings::firstLower($columnName)));

			} elseif ($column['default'] && $columnType === 'bool') {
				$content .= $this->ident(
					sprintf('private $%s = %s;', Strings::firstLower($columnName), strtoupper($column['default']))
				);

			} else {
				$content .= $this->ident(sprintf('private $%s;', Strings::firstLower($columnName)));
			}
			$content .= $this->newLine(2);
		}

		return rtrim($content);
	}


	/**
	 * @param string $string
	 * @param string $delimiter
	 * @return string
	 */
	private function camelize($string, $delimiter = '_')
	{
		$string = ucwords($string, $delimiter);
		return str_replace($delimiter, '', $string);
	}


	/**
	 * @param int $count
	 * @return string
	 */
	private function newLine($count = 1)
	{
		return str_repeat(self::NEW_LINE, $count);
	}


	/**
	 * @param string $string
	 * @param int $count
	 * @return string
	 */
	private function ident($string, $count = 1)
	{
		return Strings::indent($string, $count, self::IDENT);
	}

}
