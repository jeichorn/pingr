<?php
return [
    'default' => [
        'fail-count' => 2,
        'slow-count' => 5,
        'connect-timeout'=> 2000,
        'slow-timeout'=> 3000,
        'response-timeout'=> 10000,
        'ua'=> 'pingr/1.0 http://github.com/jeichorn/pingr',
        'valid' => [200],
    ],

    'email' => [
        'from' => '',
        'to' => '',
        'subject' => '{action}: {name}',
        'msg' => "Host {name} ({url}) has failed {count} checks\n{msg}",
    ],
    
    'pagerduty' => [
        'key' => '',
        'msg' => "{name} ({url}) is down"
    ]
];
