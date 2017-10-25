<?php

namespace TomasKarlik\EntityMapper\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use TomasKarlik\EntityMapper\EntityCreator;


final class CreateEntityCommand extends Command
{

	/**
	 * @var EntityCreator
	 */
	private $entityCreator;


	public function __construct(EntityCreator $entityCreator)
	{
		parent::__construct();

		$this->entityCreator = $entityCreator;
	}


	/**
	 * {@inheritdoc}
	 */
	protected function configure()
	{
		$this->setName('entity:create-entity')
			->setDescription('Create entity file from database table')
			->addArgument('table', InputArgument::REQUIRED, 'Table')
			->addArgument('dir', InputArgument::REQUIRED, 'Output directory');
	}


	/**
	 * {@inheritdoc}
	 */
	protected function execute(InputInterface $input, OutputInterface $output)
	{
		$table = $input->getArgument('table');
		$dir = $input->getArgument('dir');

		if ( ! file_exists($dir)) {
			$output->writeln(sprintf('Directory %s not exists!', $dir));
			return 1;
		}

		$this->entityCreator->create($table, $dir);
		return 0;
	}

}
