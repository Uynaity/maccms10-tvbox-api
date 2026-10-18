<?php
namespace think;

class Cache
{
    public static function get($key) { $GLOBALS['test_cache_key'] = $key; return false; }
    public static function set($key, $value, $ttl) {}
}

namespace app\api\controller;

error_reporting(E_ALL);
set_error_handler(function ($severity, $message, $file, $line) {
    throw new \ErrorException($message, 0, $severity, $file, $line);
});

class Base { public function __construct() {} }

function input($name = '', $default = '', $filters = '')
{
    $params = $GLOBALS['test_params'];
    array_walk_recursive($params, function (&$value) use ($filters) {
        foreach (explode(',', $filters) as $filter) {
            if (is_string($value) && in_array($filter, ['trim', 'urldecode'], true)) {
                $value = $filter($value);
            }
        }
    });
    return $params;
}

function config($name) { return $GLOBALS['test_players']; }
function mac_filter_tags($html) { return $html; }

class TestTypes
{
    public function getCache($name) { return $GLOBALS['test_types']; }
}

class TestVod
{
    public function listData($where, $order, $page, $limit, $unused, $field, $cache)
    {
        return [
            'code' => 1, 'list' => [],
            'query' => compact('where', 'order', 'page', 'limit', 'field'),
            'cache_key' => $GLOBALS['test_cache_key'],
        ];
    }
}

function model($name) { return strtolower($name) === 'type' ? new TestTypes() : new TestVod(); }

function resetFixture()
{
    $GLOBALS['test_params'] = [];
    $GLOBALS['test_types'] = [
        25 => ['type_id'=>25, 'type_pid'=>24, 'type_mid'=>1, 'type_name'=>'Mainland Variety', 'type_sort'=>20, 'type_status'=>1, 'type_extend'=>['year'=>'2026','area'=>'']],
        56 => ['type_id'=>56, 'type_pid'=>1, 'type_mid'=>1, 'type_name'=>'Netflix Movie', 'type_sort'=>0, 'type_status'=>1, 'type_extend'=>['year'=>'2023','area'=>'']],
        24 => ['type_id'=>24, 'type_pid'=>0, 'type_mid'=>1, 'type_name'=>'Variety', 'type_sort'=>5, 'type_status'=>1, 'type_extend'=>['year'=>'2024','area'=>'China']],
    ];
    $GLOBALS['config'] = [
        'api' => ['vod' => [
            'status'=>1, 'charge'=>0, 'cachetime'=>0, 'from'=>'', 'typefilter'=>'',
            'imgurl'=>'', 'datafilter'=>'', 'pagesize'=>20, 'detail_inc_hits'=>0,
        ]],
        'app' => ['cache_flag'=>'test', 'vod_extend_year'=>'2025', 'vod_extend_area'=>'China'],
        'extra' => [],
    ];
    $GLOBALS['test_players'] = [
        'source' => ['show' => 'Source'], 'sourceA' => ['show' => 'A'], 'sourceB' => ['show' => 'B'],
    ];
}

function videoFixture(): array
{
    return [
        'vod_id'=>3701, 'type_id'=>25, 'vod_time'=>1700000000,
        'vod_pic'=>'https://example.invalid/cover.jpg', 'vod_play_from'=>'sourceA$$$sourceB',
        'vod_play_url'=>'episodeA$urlA$$$episodeB$urlB',
        'vod_play_server'=>'serverA$$$serverB', 'vod_play_note'=>'noteA$$$noteB',
    ];
}

resetFixture();
