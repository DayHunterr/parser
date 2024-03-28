<?php

namespace App\Command;

proc_nice(9);

use App\Util\RunningProcessHandler;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\Process;

final class ThreadsCreatorPeniazeCommand extends Command
{
    use RunningProcessHandler;

    /**
     * Total number of concurrent processes.
     */
    private const TOTAL_THREADS = 6;

    protected static $defaultName = 'threads_creator:register.peniaze.sk';
    protected static $defaultDescription = 'Threads creator of register_peniaze_sk parser command';

    private $projectDir;

    /**
     * @param string $rootDir
     */
    public function __construct(string $rootDir)
    {
        parent::__construct();

        $this->projectDir = $rootDir;
    }

    /**
     * @inheritDoc
     */
    protected function configure(): void
    {
        $this->addOption('threads', 't', InputOption::VALUE_OPTIONAL, 'Total parallel threads.', self::TOTAL_THREADS);
    }

    /**
     * @inheritDoc
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $threads = (int)$input->getOption('threads');

        $console = $this->projectDir . '/bin/console';
        $runningProcesses = [];

        foreach (file('sitemap-or.csv', FILE_IGNORE_NEW_LINES) as $line){
            $lineOption = sprintf('--line=%s', $line);
            $commandline = ['/usr/bin/php', $console, ParserPeniazePageCommand::COMMAND_NAME, $lineOption];
//            dump($commandline[2]. ' '. $commandline[3]);
            $process = new Process($commandline);
            $process->start();

            $runningProcesses[] = $process;

            while(count($runningProcesses) === $threads){
                $this->removeFinishedProcesses($runningProcesses);
                sleep(2);
            }
        }

        while(count($runningProcesses)){
            $this->removeFinishedProcesses($runningProcesses);
            sleep(4);
        }

        return Command::SUCCESS;
    }
}
