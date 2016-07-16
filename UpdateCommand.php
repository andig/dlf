<?php

namespace Dlf;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

use Symfony\Component\DomCrawler\Crawler;

class UpdateCommand extends Command
{
    protected function configure()
    {
        $this
            ->setName('update')
            ->setDescription('Update DLF playlist catalog')
        ;
    }

    protected function parseItem($domElement) {
        $html = $domElement->ownerDocument->saveHTML($domElement);
        // echo $html;

        $crawler = new Crawler($html);
        $item = [];

        $crawler->filter('tr')->each(function (Crawler $node, $i) use (&$item, $html) {
            $td = $node->filter('td');
            if ($td->count() !== 2) {
                printf("> invalid entry: \n%s\n", $node->text());
                // echo $html;die;
                return;
            }

            $tag = utf8_decode(html_entity_decode(trim($td->eq(0)->text())));
            $value = utf8_decode(html_entity_decode(trim($td->eq(1)->text())));
            $item[$tag] = $value;
        });

        if (isset($item['Album'])) {
            if ($item['Album'] == 'Klassik - Pop - et - cetera')
                return;

            if (strpos($item['Album'], 'aus: ') === 0)
                $item['Album'] = substr($item['Album'], strlen('aus: '));

            if (preg_match('/(^.*?)\s*[,-]\s*CD\s+\d+(\/\d+)?/', $item['Album'], $m)) {
                // echo $item['Album'];
                $item['Album'] = $m[1];
            }

            if (preg_match('/^CD\s+"(.*?)"/', $item['Album'], $m)) {
                $item['Album'] = $m[1];
            }
        }

        if (isset($item['Titel'])) {
            if (strpos($item['Titel'], 'Am Mikrofon: ') !== FALSE)
                return;

            if (strpos($item['Titel'], 'aus: ') === 0)
                $item['Titel'] = substr($item['Titel'], strlen('aus: '));
        }

        foreach ($item as $tag => $value) {
            printf("%s: %s\n", $tag, $value);
        }
        echo("\n");

        return $item;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $date = new \DateTime("2013-11-16");
        $now = (new \DateTime(/*now*/))->getTimestamp();
        $items = [];

        $client = ClientFactory::getClient();

        while ($date->getTimestamp() - $now < 0) {
            $uri = sprintf('http://www.deutschlandfunk.de/playlist-klassik-pop-et-cetera.828.de.html?drpl:date=%s', $date->format("Y-m-d"));
            printf("> fetching %s from %s\n", $date->format("Y-m-d"), $uri);

            $response = $client->get($uri);
            $body = (string) $response->getBody();
            // echo $body;

            $crawler = new Crawler($body);

            foreach ($crawler->filter('ul.playlist li table') as $el) {
                // var_dump($el->nodeName);
                $item = $this->parseItem($el);
                if ($item)
                    $items[] = $item;
            }

            $date->add(new \DateInterval('P7D'));
        }

        file_put_contents(CATALOG_FILE, json_encode($items, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE));
    }
}



