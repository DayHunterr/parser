<?php

namespace App\Command;

use GuzzleHttp\Client;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DomCrawler\Crawler;

class ParserPeniazeSMCommand extends Command
{

    public const COMMAND_NAME = 'parse:register.peniazeSM.sk';
    protected function configure()
    {
        $this->setName(self::COMMAND_NAME)
            ->setDescription('This command runs parsing sitemap register.peniaze.sk')
            ->setHelp('Run this command to parse register.peniaze.sk sitemap.');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $io = new SymfonyStyle($input, $output);

        $xmlUrl = 'https://register.peniaze.sk/sitemap.xml';

        $crawler = new Crawler();
        $crawler->addXmlContent(file_get_contents($xmlUrl));

        $urls = $crawler->filter('loc')->each(function (Crawler $node) {
            return $node->text();
        });

        $fp = fopen('sitemaps.csv', 'a+');
        if ($fp) {
            foreach ($urls as $row) {
                fputcsv($fp, [$row]);
            }
            fclose($fp);
            $io->success('CSV file has been created successfully.');
        } else {
            $io->error('Failed to open CSV file for writing.');
        }

        return Command::SUCCESS;
    }
}
