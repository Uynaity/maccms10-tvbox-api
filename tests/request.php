<?php
namespace app\api\controller;

require __DIR__ . '/bootstrap.php';
require $argv[1];
$scenario = json_decode(base64_decode($argv[2]), true);
$GLOBALS['test_params'] = $scenario['params'];
$GLOBALS['config']['extra'] = $scenario['defaults'] ?? [];
(new Tvbox())->index();
