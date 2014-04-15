<?php
namespace Pingr;

use Exception;
use Symfony\Component\Console\Output\OutputInterface;


class Pinger
{
    protected $config;
    protected $hosts;
    protected $options;
    protected $output;
    protected $alerter;
    protected $db = [];

    public function __construct(OutputInterface $output, $alerter, $config, $hosts, $options)
    {
        $this->output = $output;
        $this->alerter = $alerter;
        $this->config = $config;
        $this->hosts = $hosts;
        $this->options = $options;

        if (file_exists($this->options['db']))
            $this->db = unserialize(file_get_contents($this->options['db']));
    }

    public function ping()
    {
        $multi = curl_multi_init();

        // add everything
        $handles = [];
        foreach($this->hosts as $name => $host)
        {
            $config = array_merge($this->config['default'],$host);
            $config['name'] = $name;

            $this->info("$name: $host[url]");
            $c = curl_init();
            curl_setopt($c, CURLOPT_URL, $host['url']);
            curl_setopt($c, CURLOPT_AUTOREFERER, 1);
            curl_setopt($c, CURLOPT_FOLLOWLOCATION, 1);
            curl_setopt($c, CURLOPT_FRESH_CONNECT, 1);
            curl_setopt($c, CURLOPT_USERAGENT, $config['ua']);

            curl_setopt($c, CURLOPT_CONNECTTIMEOUT_MS, $config['connect-timeout']);
            curl_setopt($c, CURLOPT_TIMEOUT_MS, $config['response-timeout']);


            curl_setopt($c, CURLOPT_SSL_VERIFYPEER, 0);
            curl_setopt($c, CURLOPT_SSL_VERIFYHOST, 0);

            curl_setopt($c, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($c, CURLOPT_HEADER, 1);

            if (!empty($host['headers']))
            {
                $headers = [];
                foreach($host['headers'] as $header => $value)
                {
                    $headers[] = "$header: $value";
                }
                var_dump($headers);
                curl_setopt($c, CURLOPT_HTTPHEADER, $headers);
            }

            $handles[intval($c)] = $c;
            curl_multi_add_handle($multi, $c);
            curl_multi_exec($multi, $active);
        }

        // execute everything
        do
        {
            $mrc = curl_multi_exec($multi, $active);
        }
        while ($mrc == CURLM_CALL_MULTI_PERFORM);

        while ($active && $mrc == CURLM_OK)
        {
            if (curl_multi_select($multi) != -1) {
                do
                {
                    $mrc = curl_multi_exec($multi, $active);
                }
                while ($mrc == CURLM_CALL_MULTI_PERFORM);
            }
        }

        // process all the results
        foreach($handles as $c)
        {
            $content = curl_multi_getcontent($c);
            $info = curl_getinfo($c);
            $this->verify($config, $this->parseMessage($content), $info);
        }

        // close everything
        foreach($handles as $c)
        {
            curl_multi_remove_handle($multi, $c);
        }
        curl_multi_close($multi);
    }

    protected function verify($config, $message, $info)
    {
        $info = $this->process($config, $message, $info);
        $info->id = $config['id'];
        if (!isset($this->db[$config['id']]))
           $this->db[$config['id']] = ['fails' => 0, 'slow' => 0, 'triggered' => [], 'recent' => []]; 

        $this->db[$config['id']]['recent'][] = $info;
        if (count($this->db[$config['id']]['recent']) > 10)
        {
            array_shift($this->db[$config['id']]['recent']);
        }

        switch($info->status)
        {
        case 'failed':
            $this->db[$config['id']]['fails']++;
            break;
        case 'ok':
            $this->db[$config['id']]['fails'] = 0;
            $this->db[$config['id']]['slow'] = 0;
            break;
        case 'slow':
            $this->db[$config['id']]['slow']++;
            break;
        }

        if ($this->db[$config['id']]['fails'] > $config['fail-count'])
        {
            $info->count = $this->db[$config['id']]['fails'];
            $this->trigger('FAILED', $config, $info);
        }
        else if ($this->db[$config['id']]['slow'] > $config['slow-count'])
        {
            $info->count = $this->db[$config['id']]['slow'];
            $this->trigger('SLOW', $config, $info);
        }
        else if ($this->db[$config['id']]['triggered'])
        {
            $this->resolve($config, $info);
        }
    }

    protected function trigger($type, $config, $info)
    {
        if (isset($this->db[$config['id']]['triggered'][$type]))
        {
            $this->alert("$config[id] still $type");
            return;
        }
        $this->alert("$config[id] $type");

        $args = [];
        foreach($info as $k => $v)
        {
            $args[$k] = $v;
        }

        $args['name'] = $config['name'];
        $args['action'] = $type;

        $args['msg'] = "Code: $args[code], Time: $args[time]";

        $this->db[$config['id']]['triggered'][$type] = $type;
        $this->alerter->trigger($config['id'], $args);
    }

    protected function resolve($config, $info)
    {
        foreach($this->db[$config['id']]['triggered'] as $type)
        {
            $args = [];
            foreach($info as $k => $v)
            {
                $args[$k] = $v;
            }

            $args['name'] = $config['name'];
            $args['action'] = $type;

            $this->alerter->resolve($config['id'], $args);
        }
    }

    protected function process($config, $message, $info)
    {
        $r = new \stdClass;
        $r->status = 'ok';
        $r->code = $info['http_code'];
        $r->url = $info['url'];
        $r->time = $info['total_time'];
        $r->connect_time = $info['connect_time'];
        $r->size = $info['size_download'];

        // check for valid return code
        if (!in_array($info['http_code'], $config['valid']))
        {
            $r->status = 'failed';
            return $r;
        }

        return $r;
    }

    //@todo find a 3rd party lib for this, not handling files termiated in just \n correctly
    protected function parseMessage($msg)
    {
        $headers = '';
        $body = '';
        $lines = explode("\r\n", $msg);
        $in_header = true;
        foreach($lines as $line)
        {
            if ($in_header && !empty($line))
            {
                $headers .= $line."\n";
            }
            else if ($in_header)
            {
                $in_header = false;
            }
            else
            {
                $body .= $line."\n";
            }

        }
        $o = new \stdClass;
        $o->headers = http_parse_headers($headers);
        $o->body = $body;

        return $o;
    }

    protected function info($msg)
    {
        $this->output->writeln("<info>$msg</info>");
    }

    protected function alert($msg)
    {
        $this->output->writeln("<error>$msg</error>");
    }

    public function __destruct()
    {
        file_put_contents($this->options['db'], serialize($this->db));
    }
}
