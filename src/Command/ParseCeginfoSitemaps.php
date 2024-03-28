<?php

namespace App\Command;

use App\Util\CrawlerWrapper;
use App\Util\ProxyRandomizer;
use App\Util\RequestAttempt;
use App\Util\Writer;
use GuzzleHttp\Client;
use GuzzleHttp\Cookie\CookieJar;
use GuzzleHttp\Cookie\FileCookieJar;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class ParseCeginfoSitemaps extends Command
{

    use ProxyRandomizer, RequestAttempt;

    public const COMMAND_NAME = "parse:ceginfo.hu";
    private const BASE_URI = 'https://ceginfo.hu/';
    private const SITES = self::BASE_URI . 'sitemap.xml';
    private const SEARCH_FORM_URI = self::BASE_URI . 'cegkereso/rapid';
    private const RESULT_PATTERN = "%s}##{%s}##{%s}##{%s}##{%s}##{%s}##{%s\n";
    private const ERROR_PATTERN = "%s}##{%s\n";
    private $client;

    public function __construct()
    {
        parent::__construct();
        $this->client = new Client([
            'headers' => [
                'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,*/*;q=0.8',
                'Accept-Encoding' => 'gzip, deflate, br',
                'Accept-Language' => 'en-US,en;q=0.5',
                'Connection' => 'keep-alive',
                'User-Agent' => 'Mozilla/5.0 (X11; Ubuntu; Linux x86_64; rv:123.0) Gecko/20100101 Firefox/123.0'
            ],
        ]);
        $this->proxies = file('proxies.txt', FILE_IGNORE_NEW_LINES);
    }

    protected function configure(): void
    {
        $this
            ->setName(self::COMMAND_NAME)
            ->setDescription('This command runs parsing parse:ceginfo.hu')
            ->setHelp('Run this command to execute your custom tasks in the execute function.')
            ->addOption('combination', 'c', InputOption::VALUE_REQUIRED, 'Characters combination');
    }


    protected function execute(InputInterface $input, OutputInterface $output): int
    {

        $resultWriter = new Writer('result/hu/ceginfo/result.csv');
        $errorWriter = new Writer('result/hu/ceginfo/error.csv');
        $closedCoWriter = new Writer('result/hu/ceginfo/closed.csv');

//        $sitemaps = ["sitemap_01_1.xml","sitemap_01_2.xml","sitemap_01_3.xml","sitemap_01_4.xml","sitemap_01_5.xml","sitemap_02_1.xml","sitemap_03_1.xml",
//        "sitemap_04_1.xml","sitemap_05_1.xml","sitemap_06_1.xml","sitemap_07_1.xml","sitemap_08_1.xml","sitemap_09_1.xml","sitemap_10_1.xml","sitemap_11_1.xml",
//        "sitemap_12_1.xml","sitemap_13_1.xml","sitemap_13_2.xml","sitemap_14_1.xml","sitemap_15_1.xml","sitemap_16_1.xml","sitemap_17_1.xml","sitemap_18_1.xml",
//            "sitemap_19_1.xml","sitemap_20_1.xml"];
//
//
//    foreach ($sitemaps as $key => $link){
//            $io->success($key);
//            $stream = fopen("/var/www/html/parser/CeginfoSitemaps/sitemap_01_$key.csv", 'a+');
//            $res = (string)$this->client->get(self::BASE_URI.$link)->getBody();
//            $crawler = new CrawlerWrapper($res);
//
//            $profileRefs = $crawler->filter('url loc')->each(function (CrawlerWrapper $ref) use ($stream){
//                fwrite($stream, $ref->text() . PHP_EOL);
//            });
//
//            fclose($stream);
//    }

//                $line = $input->getOption('profiles');
//                $line = 'https://ceginfo.hu/ceglista/cegek';

        $chars = $input->getOption('combination');

        $cookieFilePath = 'cookies/hu/ceginfo/cookies.txt';
        $cookieJar = new FileCookieJar($cookieFilePath, true);

        try {
            $response = $this->sendGETRequest(self::BASE_URI, [
                'proxy' => $this->getRandomProxy(),
                'cookies' => $cookieJar,
            ]);

            if ($response->getStatusCode() !== 200) {
                $errorWriter->write(sprintf(self::ERROR_PATTERN, self::BASE_URI, $response->getStatusCode()));
                return Command::SUCCESS;
            }

            foreach ($cookieJar as $cookie) {
                if ($cookie->getName() === 'ceginfo_csrf_token') {
                    $csrfToken = $cookie->getValue();
                    break;
                } else {
                    $csrfToken = null;
                }
            }

            try {
                $data = [
                    'ceginfo_csrf_token' => $csrfToken,
                    'rapid' => $chars,
                    'honeypot' => '',
                ];

                $response = $this->sendPOSTRequest(self::SEARCH_FORM_URI, [
                    'proxy' => $this->getRandomProxy(),
                    'form_params' => $data,
                    'cookies' => $cookieJar,
                    'allow_redirects' => false,
                ]);
                if ($response->getStatusCode() !== 303) {
                    $errorWriter->write(sprintf(self::ERROR_PATTERN, self::SEARCH_FORM_URI, $response->getStatusCode()));
                    return Command::SUCCESS;
                }
                $redirectUrl = $response->getHeaderLine('Location');

                for ($page = 1; $page < 50000; $page++) {
                    $paginationUrl = $redirectUrl . '/' . $page;
                    try {
                        $response = $this->sendGETRequest($paginationUrl, [
                            'proxy' => $this->getRandomProxy(),
                            'cookies' => $cookieJar,
                            'allow_redirects' => false,
                        ]);

                        if ($response->getStatusCode() !== 200) {
                            $errorWriter->write(sprintf(self::ERROR_PATTERN, $paginationUrl, $response->getStatusCode()));
                            continue;
                        }

                        $crawler = new CrawlerWrapper((string)$response->getBody());
                        $rows = $crawler->filterXPath('//*[@id="talalati-lista"]/div[2]/div[1]/div/div/div[3]/div');
                        $rows->each(function (CrawlerWrapper $row) use ($resultWriter, $errorWriter, $closedCoWriter) {
                            $profileLink = $row->getNodeAttr('a', 'href');
                            $year = $row->getNodeText('h2 > small');
                            $name = str_replace($year, '', $row->getNodeText('h2.s-title'));
                            $address = $row->getNodeText('h2.s-title + p');
                            if (!$address || $address === '-' || !$name || $name === '-') {
                                $errorWriter->write(sprintf(self::ERROR_PATTERN, $profileLink, "Invalid address"));
                            }

                            $postalCode = trim($this->extractPostalCode($address));
                            $city = trim($this->extractCity($address));
                            $street = trim($this->extractStreet($address));
                            $nip = $this->deleteBeforeColon($row->getEqNodeText('h2.s-title + p + div > p > span', 0));
                            $regN = $this->deleteBeforeColon($row->getEqNodeText('h2.s-title + p + div > p > span', 1));
                            $companyData = sprintf(self::RESULT_PATTERN, $name, $nip, $year, $street, $city, $postalCode, $regN);

                            $row->getNodeText('div.status.stategreen')
                                ? $resultWriter->write($companyData)
                                : $closedCoWriter->write($companyData);
                        });
                    } catch (\Throwable $ex) {
                        $errorWriter->write(sprintf(self::ERROR_PATTERN, $paginationUrl, $ex->getMessage()));
                        return Command::SUCCESS;
                    }
                }
            } catch (\Throwable $ex) {
                $errorWriter->write(sprintf(self::ERROR_PATTERN, self::SEARCH_FORM_URI, $ex->getMessage()));
                return Command::SUCCESS;
            }
        } catch (\Throwable $ex) {
            $errorWriter->write(sprintf(self::ERROR_PATTERN, self::BASE_URI, $ex->getMessage()));
            return Command::SUCCESS;
        }
        return Command::SUCCESS;
    }

    /**
     * @param string|null $address
     *
     * @return string|null
     */
    private function extractStreet(?string $address): ?string
    {
        $addrParts = explode(',', $address);

        return $addrParts[1] ?? null;
    }

    /**
     * @param string|null $address
     *
     * @return string|null
     */
    private function extractCity(?string $address): ?string
    {
        $addrParts = explode(' ', $address);
        $city = $addrParts[1] ?? null;

        return str_replace(',', '', $city);
    }

    /**
     * @param string|null $address
     *
     * @return string|null
     */
    private function extractPostalCode(?string $address): ?string
    {
        $addrParts = explode(' ', $address);

        return $addrParts[0] ?? null;
    }

    /**
     * @param string|null $input
     *
     * @return string|null
     */
    private function deleteBeforeColon(?string $input): ?string {
        $pattern = '/.*?:/';

        $result = preg_replace($pattern, '', $input, 1);

        return $result !== null ? trim($result) : null;
    }

//    private function getArchives(): array
//    {
//        $content = (string)$this->client->request('GET', self::SITES)->getBody();
//        $crawler = new CrawlerWrapper($content);
//
//        return $crawler->filter('sitemapindex sitemap loc')->each(function (CrawlerWrapper $item) {
//            return substr($item->text(), 0, -3);
//        });
//    }
//        return self::SUCCESS;
//    }

}
