<?php

namespace App\Command;

use GuzzleHttp\Client;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DomCrawler\Crawler;


class ParserCommand extends Command
{


    protected function configure(): void
    {
        // Use in-build functions to set name, description and help
        $this->setName('parse:financy.bg')
            ->setDescription('This command runs parsing financy.bg')
            ->setHelp('Run this command to execute your custom tasks in the execute function.');
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

        $links = file('output.csv', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
//        $xmlUrl = 'https://finansi.bg/sitemaps/1.xml';
        foreach ($links as $xmlUrl) {
            $response = $client->get($xmlUrl);

            $xml = $response->getBody()->getContents();


            $crawler = new Crawler($xml);

            $filter = $crawler->filter('url loc');

            $data = $filter->each(function (Crawler $row) use ($client) {

                return $row->filter('loc')->text();

            });

            $fp = fopen('output.csv', 'a+');
            if ($fp) {
                foreach ($data as $row) {
                    fputcsv($fp, [$row]);
                }
                fclose($fp);
                $io->success('CSV file has been created successfully.');
            } else {
                $io->error('Failed to open CSV file for writing.');
            }

            function getSitemaps($xmlUrl): array
            {
                $client = new Client();
                $response = $client->get($xmlUrl);
                $xml = $response->getBody()->getContents();

                $domCrawler = new Crawler($xml);
                $sitemaps = $domCrawler->filter('loc');

                $sitemapUrls = [];
                $sitemaps->each(function (Crawler $sitemap) use (&$sitemapUrls) {
                    $sitemapUrls[] = $sitemap->text();
                });
                return $sitemapUrls;
            }

            function getFirstFiveRawPages($xmlUrl): array
            {
                $client = new Client();
                $response = $client->get($xmlUrl);
                $xml = $response->getBody()->getContents();

                $domCrawler = new Crawler($xml);
                $sitemaps = $domCrawler->filter('loc');

                $sitemapUrls = [];
                $sitemaps->each(function (Crawler $sitemap) use (&$sitemapUrls) {
                    $sitemapUrls[] = $sitemap->text();
                });

                return array_slice($sitemapUrls, 1, 4);
            }
        }
        return Command::SUCCESS;
    }
}