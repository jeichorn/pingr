<?php
namespace Pingr;

use SimpleMail;

class EmailAlerter
{
    protected $config;
    public function __construct($config)
    {
        $this->config = $config;
    }

    public function trigger($key, $args)
    {
        if ($this->config === false)
            return;

        $args['action'] = 'TRIGGERED';
        $subject = str_replace(
            array_map(function ($i) { return '{'.$i.'}'; }, array_keys($args)),
            array_values($args),
            $this->config['subject']
        );

        $msg = str_replace(
            array_map(function ($i) { return '{'.$i.'}'; }, array_keys($args)),
            array_values($args),
            $this->config['msg']
        );

        $this->mail($subject, $msg);
    }

    public function resolve($key, $args)
    {
        if ($this->config === false)
            return;

        $args['action'] = 'RESOLVED';
        $subject = str_replace(
            array_map(function ($i) { return '{'.$i.'}'; }, array_keys($args)),
            array_values($args),
            $this->config['subject']
        );
        $msg = str_replace(
            array_map(function ($i) { return '{'.$i.'}'; }, array_keys($args)),
            array_values($args),
            $this->config['msg']
        );
        $this->mail($subject, $msg);
    }

    protected function mail($subject, $msg)
    {
        $mailer = new SimpleMail();
        $mailer
            ->setTo($this->config['to'], '')
            ->setFrom($this->config['from'], '')
            ->setSubject($subject)
            ->setMessage($msg)
            ->send();
    }
}
