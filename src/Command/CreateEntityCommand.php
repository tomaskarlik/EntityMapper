<?php

declare(strict_types = 1);

namespace TomasKarlik\EntityMapper\Command;

use Exception;
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
	protected function configure(): void
	{
		$this->setName('entity:create-entity')
			->setDescription('Create entity file from database table')
			->addArgument('table', InputArgument::REQUIRED, 'table')
			->addArgument('chmod', InputArgument::OPTIONAL, 'chmod', 0755);
	}


	/**
	 * {@inheritdoc}
	 */
	protected function execute(InputInterface $input, OutputInterface $output): ?int
	{
		$table = $input->getArgument('table');
		$chmod = $input->getArgument('chmod');

		try {
			$this->entityCreator->create($table, $chmod);

		} catch (Exception $exception) {
			$output->writeln(sprintf('Error: %s', $exception->getMessage()));
			return 1;
		}

		return 0;
	}

}
