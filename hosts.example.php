<?php
return [
    'test' => [
        'id' => 'test',
        'tags' => ['test'],
        'url' => 'http://localhost/',
        'headers' => [
            'X-Foo' => 'bar',
            ],
        'verify' => [
            'headers' => ['X-Foo' => 'bar'],
            'content' => 'foo'
        ]
    ]
];
