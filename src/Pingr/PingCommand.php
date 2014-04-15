<?php
namespace Pingr;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Exception;

class PingCommand extends Command
{
    public function __construct()
    {
        parent::__construct();
    }

    protected function configure()
    {
        $this
            ->setName('ping')
            ->setDescription('Ping all configured hosts')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, "Just print alerts, don't send them to pagerduty")
            ->addOption('hosts', null, InputOption::VALUE_REQUIRED, "Path to hosts config")
            ->addOption('config', null, InputOption::VALUE_REQUIRED, "Path to config file")
            ->addOption('db', null, InputOption::VALUE_REQUIRED, "Path to db file")
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $options = $input->getOptions();

        if (empty($options['hosts']))
            $options['hosts'] = realpath(__DIR__.'/../../hosts.php');
        if (empty($options['config']))
            $options['config'] = realpath(__DIR__.'/../../config.php');
        if (empty($options['db']))
            $options['db'] = '/tmp/pingr.db';

        if (!file_exists($options['hosts']))
        {
            throw new Exception("Unable to load host config: {$options['hosts']}");
        }
        $hosts = include $options['hosts'];

        if (!file_exists($options['config']))
        {
            throw new Exception("Unable to load config: {$options['config']}");
        }
        $config = include $options['config'];

 

        $alterer = new MultiAlerter([
                        new PagerDutyAlerter($config['pagerduty']),
                        new EmailAlerter($config['email']),
                   ]);

        $pinger = new Pinger($output, $alterer, $config, $hosts, $options);

        $pinger->ping();
    }
}
