<?php
declare(strict_types=1);

namespace DatabaseToClass\Console;

use DatabaseToClass\ClassGenerator;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Console\Question\ChoiceQuestion;

class GenerateCommand extends Command
{
    protected static $defaultName = 'generate';
    protected static $defaultDescription = 'Generate PHP class from database table';

    protected function configure(): void
    {
        $this
            ->setHelp('This command allows you to generate PHP classes from your database tables')
            ->addArgument('table', InputArgument::OPTIONAL, 'Table name to generate class for')
            ->addOption('all', 'a', InputOption::VALUE_NONE, 'Generate classes for all tables')
            ->addOption('output', 'o', InputOption::VALUE_REQUIRED, 'Output directory', 'GeneratedClasses');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $io->title('Database to Class Generator');

        try {
            $generator = new ClassGenerator();
            $tables = $generator->getTables();

            if (empty($tables)) {
                $io->error('No tables found in database');
                return Command::FAILURE;
            }

            $outputDir = $input->getOption('output');

            // Check if output directory is writable
            if (!is_dir($outputDir)) {
                mkdir($outputDir, 0777, true);
            }

            if (!is_writable($outputDir)) {
                $io->error("Output directory '{$outputDir}' is not writable. Try: chmod 777 {$outputDir}");
                return Command::FAILURE;
            }

            // Generate all tables
            if ($input->getOption('all')) {
                return $this->generateAllTables($io, $generator, $tables, $outputDir);
            }

            // Interactive table selection
            $tableName = $input->getArgument('table');
            if (!$tableName) {
                $tableName = $this->selectTable($io, $tables);
                if (!$tableName) {
                    return Command::FAILURE;
                }
            }

            // Generate single table
            return $this->generateTable($io, $generator, $tableName, $outputDir);

        } catch (\Exception $e) {
            $io->error('Error: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }

    private function selectTable(SymfonyStyle $io, array $tables): ?string
    {
        $tableNames = [];
        $tableInfo = [];

        foreach ($tables as $table) {
            if (!isset($table['primaryKey']) || empty($table['primaryKey'])) {
                continue; // Skip tables without primary keys
            }
            $tableNames[] = $table['tableName'];
            $tableInfo[$table['tableName']] = $table;
        }

        if (empty($tableNames)) {
            $io->error('No tables with primary keys found');
            return null;
        }

        $helper = $this->getHelper('question');
        $question = new ChoiceQuestion(
            'Please select a table to generate:',
            $tableNames,
            0
        );
        $question->setErrorMessage('Table %s is invalid.');

        return $helper->ask($io->getInput(), $io->getOutput(), $question);
    }

    private function generateTable(SymfonyStyle $io, ClassGenerator $generator, string $tableName, string $outputDir): int
    {
        $io->section("Generating class for table: {$tableName}");

        $generator->setTable($tableName);
        $classCode = $generator->buildClass();

        $filename = $outputDir . DIRECTORY_SEPARATOR . $tableName . '.php';
        file_put_contents($filename, $classCode);

        $io->success("Generated: {$filename}");

        // Show relationship info if any
        $relationshipInfo = $generator->getRelationshipInfo();
        if (!empty($relationshipInfo)) {
            $io->writeln($relationshipInfo);
        }

        return Command::SUCCESS;
    }

    private function generateAllTables(SymfonyStyle $io, ClassGenerator $generator, array $tables, string $outputDir): int
    {
        $io->section('Generating classes for all tables');

        $validTables = array_filter($tables, function($table) {
            return isset($table['primaryKey']) && !empty($table['primaryKey']);
        });

        if (empty($validTables)) {
            $io->error('No tables with primary keys found');
            return Command::FAILURE;
        }

        $io->progressStart(count($validTables));

        $generated = 0;
        $skipped = 0;

        foreach ($validTables as $table) {
            $tableName = $table['tableName'];

            try {
                $generator->setTable($tableName);
                $classCode = $generator->buildClass();

                $filename = $outputDir . DIRECTORY_SEPARATOR . $tableName . '.php';
                file_put_contents($filename, $classCode);

                $generated++;
                $io->progressAdvance();
            } catch (\Exception $e) {
                $skipped++;
                $io->warning("Skipped {$tableName}: " . $e->getMessage());
            }
        }

        $io->progressFinish();

        $io->success("Generated {$generated} classes, skipped {$skipped}");
        $io->writeln("Output directory: {$outputDir}");

        return Command::SUCCESS;
    }
}
