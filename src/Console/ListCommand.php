<?php
declare(strict_types=1);

namespace DatabaseToClass\Console;

use DatabaseToClass\ClassGenerator;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Console\Helper\Table;

class ListCommand extends Command
{
    protected static $defaultName = 'list-tables';
    protected static $defaultDescription = 'List all database tables';

    protected function configure(): void
    {
        $this->setHelp('This command displays all tables in your database with their primary keys');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $io->title('Database Tables');

        try {
            $generator = new ClassGenerator();
            $tables = $generator->getTables();

            if (empty($tables)) {
                $io->warning('No tables found in database');
                return Command::SUCCESS;
            }

            $table = new Table($output);
            $table->setHeaders(['Table Name', 'Primary Key', 'Status']);

            foreach ($tables as $tableInfo) {
                $status = isset($tableInfo['primaryKey']) && !empty($tableInfo['primaryKey'])
                    ? '<info>✓ Ready</info>'
                    : '<comment>⚠ No Primary Key</comment>';

                $table->addRow([
                    $tableInfo['tableName'],
                    $tableInfo['primaryKey'] ?? '<fg=red>-</>',
                    $status
                ]);
            }

            $table->render();

            $io->note('Tables with primary keys can be used to generate classes');
            $io->writeln('Run: <info>php generate.php generate <table-name></info> to generate a class');

            return Command::SUCCESS;

        } catch (\Exception $e) {
            $io->error('Error: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }
}
