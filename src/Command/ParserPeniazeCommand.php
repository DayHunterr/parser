<?php

namespace App\Command;

use GuzzleHttp\Client;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DomCrawler\Crawler;

class ParserPeniazeCommand extends Command
{

    public const COMMAND_NAME = 'parse:register.peniaze.sk';

    protected function configure()
    {
        $this->setName(self::COMMAND_NAME)
            ->setDescription('This command runs reading sitemaps and pull links from it to files')
            ->setHelp('Run this command to read sitemap links from file and get profile links from it.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $client = new Client([
            'headers' => [
                'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,*/*;q=0.8',
                'Accept-Encoding' => 'gzip, deflate, br',
                'Accept-Language' => 'en-US,en;q=0.5',
                'Connection' => 'keep-alive',
                'User-Agent' => 'Mozilla/5.0 (X11; Ubuntu; Linux x86_64; rv:123.0) Gecko/20100101 Firefox/123.0'
            ]
        ]);

        $links = file('sitemaps.csv', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

        $files = [
            'sitemap-or.csv',
            'sitemap-zr.csv',
            'sitemap-person.csv'
        ];

        foreach ($links as $xmlUrl) {
            $group = $this->determineGroup($xmlUrl);

            if ($group === -1) {
                $io->error("Failed to determine group for URL: {$xmlUrl}");
                continue;
            }

            $response = $client->get($xmlUrl);
            $xml = $response->getBody()->getContents();

            $crawler = new Crawler($xml);
            $filter = $crawler->filter('url loc');

            $data = $filter->each(function (Crawler $row) use ($client) {
                return $row->filter('loc')->text();
            });

            $fp = fopen($files[$group], 'a+');
            if ($fp) {
                foreach ($data as $row) {
                    fputcsv($fp, [$row]);
                }
                fclose($fp);
                $io->success('CSV file has been created successfully.');
            } else {
                $io->error('Failed to open CSV file for writing.');
            }

        }
        return Command::SUCCESS;
    }

    private function determineGroup(string $url): int
    {
        if (strpos($url, 'sitemap-or') !== false) {
            return 0;
        } elseif (strpos($url, 'sitemap-zr') !== false) {
            return 1;
        } elseif (strpos($url, 'sitemap-person') !== false) {
            return 2;
        } else {
            return -1;
        }
    }
}
