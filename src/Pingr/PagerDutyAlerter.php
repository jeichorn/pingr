<?php
namespace Pingr;

use PagerDuty\PagerDutyEvent;

class PagerDutyAlerter
{
    protected $config;
    public function __construct($config)
    {
        $this->api = new PagerDutyEvent($config['key']);
        $this->config = $config;
    }

    public function trigger($key, $args)
    {
        if ($this->config === false)
            return;
        $this->api->trigger($key, str_replace(
            array_map(function ($i) { return '{'.$i.'}'; }, array_keys($args)),
            array_values($args),
            $this->config['msg']
        ));
    }

    public function resolve($key, $args)
    {
        if ($this->config === false)
            return;
        $this->api->resolve($key, str_replace(
            array_map(function ($i) { return '{'.$i.'}'; }, array_keys($args)),
            array_values($args),
            $this->config['msg']
        ));
    }
}
