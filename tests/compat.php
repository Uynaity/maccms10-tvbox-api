<?php
namespace app\api\controller;

class Base { public function __construct() {} }
function input() { return $GLOBALS['test_params']; }
function config($name) { return ['source' => ['show' => 'Source']]; }
class TestTypes {
    public function getCache($name) { return $GLOBALS['test_types']; }
}
function model($name) { return new TestTypes(); }
function check($condition, $label) {
    if (!$condition) { throw new \RuntimeException($label); }
    echo "PASS: $label\n";
}
require $argv[1];

$GLOBALS['test_params'] = ['f' => '{"year":"2024","st":"25","isend":0,"ids":"999"}'];
$api = new Tvbox();
check($api->_param['year'] === '2024' && $api->_param['st'] === '25', 'JSON filters accepted');
check($api->_param['isend'] === '0', 'unfinished filter zero preserved');
check(!isset($api->_param['ids']), 'non-filter fields not imported');

$GLOBALS['test_params'] = ['year' => '2025', 'area' => '', 'f' => '{"year":"2024","area":"China"}'];
$api = new Tvbox();
check($api->_param['year'] === '2025' && $api->_param['area'] === '', 'flat filters take precedence including empty');

$GLOBALS['test_params'] = ['f' => '{invalid'];
$api = new Tvbox();
check(!isset($api->_param['year']), 'invalid JSON ignored');
$GLOBALS['test_params'] = ['f' => '{"year":["2024"]}'];
$api = new Tvbox();
check(!isset($api->_param['year']), 'non-scalar filter ignored');

$GLOBALS['test_types'] = [
    25 => ['type_id'=>25, 'type_pid'=>24, 'type_name'=>'Mainland Variety', 'type_sort'=>20, 'type_status'=>1, 'type_extend'=>['year'=>'2026','area'=>'']],
    56 => ['type_id'=>56, 'type_pid'=>1, 'type_name'=>'Netflix Movie', 'type_sort'=>0, 'type_status'=>1, 'type_extend'=>['year'=>'2023','area'=>'']],
    24 => ['type_id'=>24, 'type_pid'=>0, 'type_name'=>'Variety', 'type_sort'=>5, 'type_status'=>1, 'type_extend'=>['year'=>'2024','area'=>'China']],
];
$GLOBALS['config'] = [
    'api'=>['vod'=>['from'=>'', 'typefilter'=>'', 'imgurl'=>'']],
    'app'=>['vod_extend_year'=>'2025', 'vod_extend_area'=>'China'],
];
$GLOBALS['test_params'] = ['ac' => '', 'at' => 'json'];
$api = new Tvbox();
$data = $api->vod_json(['list'=>[[
    'vod_id'=>3701, 'type_id'=>25, 'vod_time'=>1700000000,
    'vod_pic'=>'https://example.invalid/cover.jpg', 'vod_play_from'=>'source',
]]]);
check($data['list'][0]['type_name'] === 'Mainland Variety', 'sorting preserves category ID lookup');
check($data['filters'][24][1]['value'][0]['v'] === '2024', 'parent category extension lookup preserved');
check($data['filters'][24][0]['value'][0]['v'] === 25, 'child category ID unchanged');
echo "ALL TESTS PASSED\n";
