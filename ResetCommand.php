<?php

namespace Dlf;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

use Doctrine\Common\Cache\FilesystemCache;


class ResetCommand extends Command
{
    protected function configure()
    {
        $this
            ->setName('reset')
            ->setDescription('Reset local cache')
            ->addArgument(
                'playlist',
                InputArgument::OPTIONAL,
                'Playlist name',
                'klassik-pop-et-cetera'
            )
            ->addOption(
               'browser',
               null,
               InputOption::VALUE_NONE,
               'Clear browser cache'
            )
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        if (file_exists(CACHE_FILE)) {
            unlink(CACHE_FILE);
        }

        if (file_exists($file = $playlistName = $input->getArgument('playlist') . '.json')) {
            unlink($file);
        }

        if ($input->getOption('browser')) {
            $cacheProvider = new FilesystemCache(__DIR__ . '/cache');
            var_dump($cacheProvider->deleteAll());

            // rmdir(__DIR__ . '/cache');
        }
    }
}



