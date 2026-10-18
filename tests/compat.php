<?php
namespace app\api\controller;

require __DIR__ . '/bootstrap.php';
$controller = realpath($argv[1] ?? dirname(__DIR__) . '/Tvbox.php');
require $controller;
$checks = 0;

function check($condition, $label)
{
    global $checks;
    if (!$condition) { throw new \RuntimeException($label); }
    $checks++;
    echo "PASS: $label\n";
}

// index() calls exit, so exercise it in a child process with a model fixture.
function request(array $params = [], array $defaults = []): array
{
    global $controller;
    $command = [PHP_BINARY, __DIR__ . '/request.php', $controller,
        base64_encode(json_encode(compact('params', 'defaults')))];
    $process = proc_open($command, [0=>['pipe','r'], 1=>['pipe','w'], 2=>['pipe','w']], $pipes);
    fclose($pipes[0]);
    $output = stream_get_contents($pipes[1]);
    $error = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    if (proc_close($process) !== 0) { throw new \RuntimeException($error); }
    $data = json_decode($output, true);
    if (!is_array($data)) { throw new \RuntimeException('Invalid response: ' . $output); }
    return $data;
}

$GLOBALS['test_params'] = ['f' => '{"year":"2024","st":"25","isend":0,"ids":"999"}'];
$api = new Tvbox();
check($api->_param['year'] === '2024' && $api->_param['st'] === '25', 'JSON filters accepted');
check($api->_param['isend'] === '0', 'unfinished filter zero preserved');
check(!isset($api->_param['ids']), 'non-filter fields not imported');

$GLOBALS['test_params'] = ['year'=>'2025', 'area'=>'', 'f'=>'{"year":"2024","area":"China"}'];
$api = new Tvbox();
check($api->_param['year'] === '2025' && $api->_param['area'] === '', 'flat filters take precedence including empty');
foreach (['{invalid', 'null', '[1,2]', '{"year":["2024"]}'] as $invalid) {
    $GLOBALS['test_params'] = ['f' => $invalid];
    check(!isset((new Tvbox())->_param['year']), 'invalid or non-scalar JSON ignored: ' . $invalid);
}
$GLOBALS['test_params'] = ['wd'=>'A+B%20'];
check((new Tvbox())->_param['wd'] === 'A+B%20', 'search text is not URL-decoded twice');

resetFixture();
$api = new Tvbox();
$data = $api->vod_json(['list'=>[videoFixture()]]);
check($data['list'][0]['type_name'] === 'Mainland Variety', 'sorting preserves category ID lookup');
check($data['filters'][24][1]['value'][0]['v'] === '2024', 'parent category extension lookup preserved');
check($data['filters'][24][0]['value'][0]['v'] === 25, 'child category ID unchanged');
check($data['list'][0]['vod_id'] === 3701, 'building categories does not mutate the last video');
$orphan = videoFixture();
$orphan['type_id'] = 999;
check($api->vod_json(['list'=>[$orphan]])['list'][0]['type_name'] === '', 'deleted category does not break results');
check($api->convertPlayerName(['unknown']) === ['unknown'], 'unknown player keeps its source name');

$GLOBALS['config']['api']['vod']['from'] = 'sourceB,sourceA,sourceB,missing';
$api->_param['ac'] = 'detail';
$video = $api->vod_json(['list'=>[videoFixture()]])['list'][0];
check($video['vod_play_from'] === 'B$$$A', 'selected source labels remain separate');
check($video['vod_play_url'] === 'episodeB$urlB$$$episodeA$urlA', 'selected URLs match source order');
check($video['vod_play_server'] === 'serverB$$$serverA' && $video['vod_play_note'] === 'noteB$$$noteA', 'source metadata stays aligned');
$short = videoFixture();
unset($short['vod_play_server'], $short['vod_play_note']);
$video = $api->vod_json(['list'=>[$short]])['list'][0];
check($video['vod_play_server'] === '$$$' && $video['vod_play_note'] === '$$$', 'missing source metadata keeps empty slots');
$api->_param['ac'] = '';
unset($short['vod_play_url']);
$video = $api->vod_json(['list'=>[$short]])['list'][0];
check($video['vod_play_from'] === 'B$$$A' && !isset($video['vod_play_url']), 'compact source filtering needs no detail fields');

check($api->vod_url_deal('urlA$$$urlB', 'sourceA$$$sourceB', 'sourceB', 'json') === ['sourceB'=>'urlB'], 'source filter works on PHP 7.4');
check($api->vod_url_deal('urlA$$$urlB', 'sourceA$$$sourceAB', 'sourceAB', 'json') === ['sourceAB'=>'urlB'], 'source names match exactly');
check($api->vod_url_deal('urlA', 'sourceA$$$sourceB', '', 'json') === ['sourceA'=>'urlA'], 'missing URL group is skipped');
$xml = $api->vod_url_deal('episode$https://example.invalid/?a=1&b=2]]>end', 'source"&', '', 'xml');
$parsed = simplexml_load_string('<dl>' . $xml . '</dl>');
check((string)$parsed->dd['flag'] === 'source"&' && (string)$parsed->dd === 'episode$https://example.invalid/?a=1&b=2]]>end', 'XML source attributes and CDATA remain valid');

resetFixture();
$api = new Tvbox();
$api->_param['ac'] = 'detail';
$GLOBALS['test_types'][25]['type_name'] = 'Variety & Music';
$video = videoFixture() + ['vod_name'=>'Name]]> & <', 'vod_remarks'=>'Remark]]>', 'vod_lang'=>'Chinese & English',
    'vod_area'=>'A&B', 'vod_year'=>'2024', 'vod_serial'=>'1', 'vod_actor'=>'Actor]]>',
    'vod_director'=>'Director]]>', 'vod_content'=>'Description]]>'];
$video['vod_pic'] = 'https://example.invalid/?a=1&b=2';
$response = ['page'=>1, 'pagecount'=>1, 'limit'=>20, 'total'=>1, 'list'=>[$video]];
$xml = simplexml_load_string($api->vod_xml($response));
check((string)$xml->list->video->type === 'Variety & Music' && (string)$xml->list->video->pic === $video['vod_pic'], 'XML detail escapes category and cover text');
check((string)$xml->list->video->name === $video['vod_name'] && (string)$xml->list->video->des === $video['vod_content'], 'XML detail preserves CDATA terminators');
$api->_param['ac'] = '';
$xml = simplexml_load_string($api->vod_xml($response));
check((string)$xml->class->ty[0] === 'Variety & Music', 'XML classification names are escaped');
$video['vod_pic'] = 'mac://example.invalid/cover.jpg';
check($api->vod_json(['list'=>[$video]])['list'][0]['vod_pic'] === 'http://example.invalid/cover.jpg', 'mac cover prefix uses configured protocol');
$GLOBALS['config']['api']['vod']['imgurl'] = 'https://example.invalid';
$video['vod_pic'] = '/cover.jpg';
check($api->vod_json(['list'=>[$video]])['list'][0]['vod_pic'] === 'https://example.invalid/cover.jpg', 'relative cover uses configured image base');

$data = request();
check($data['code'] === 1 && isset($data['class'], $data['filters']), 'bare homepage request succeeds without notices');
check(strpos($data['query']['field'], 'vod_pic') !== false && strpos($data['query']['field'], 'vod_year') !== false, 'compact query includes cover and year');
$data = request(['ac'=>'detail', 't'=>'24', 'f'=>'{"year":"2024","st":"25","isend":0}']);
check($data['query']['where']['vod_year'] === ['in',['2024']] && $data['query']['where']['type_id'] === '25' && $data['query']['where']['vod_isend'] === 0, 'public entry point applies JSON year, subtype and unfinished filters');
check($data['query']['field'] === '*', 'detail request retains playback fields');
$defaults = ['tvbox_default_year'=>'2026', 'tvbox_default_area'=>'China'];
$data = request(['ac'=>'detail', 't'=>'24', 'year'=>'2024', 'area'=>''], $defaults);
check($data['query']['where']['vod_year'] === ['in',['2024']] && !isset($data['query']['where']['vod_area']), 'defaults do not override explicit filters or empty area');
$data = request(['ac'=>'detail', 't'=>'24'], $defaults);
check($data['query']['where']['vod_year'] === ['in',['2026']] && $data['query']['where']['vod_area'] === ['like','%China%'], 'unfiltered category uses configured defaults');
$changed = request(['ac'=>'detail', 't'=>'24'], ['tvbox_default_year'=>'2025']);
check($changed['cache_key'] !== $data['cache_key'], 'cache identity includes effective defaults');
$data = request(['ac'=>'detail', 't'=>'24', 'f'=>'{}'], $defaults);
check(!isset($data['query']['where']['vod_year']) && !isset($data['query']['where']['vod_area']), 'explicit empty JSON filters suppress defaults');
foreach (['vod_time desc, arbitrary_expression', 'unknown_column', ['vod_hits']] as $invalid) {
    check(request(['order_by'=>$invalid])['query']['order'] === 'vod_time desc', 'unsupported sort input falls back to update time');
}
check(request(['order_by'=>'vod_hits'])['query']['order'] === 'vod_hits desc', 'supported sort field is retained');
check(!isset(request(['st'=>['exp','arbitrary_expression']])['query']['where']['type_id']), 'array filter cannot become an ORM expression');
foreach (['', 'invalid', '2'] as $invalid) {
    check(!isset(request(['isend'=>$invalid])['query']['where']['vod_isend']), 'invalid or cleared completion filter is ignored');
}
check(request(['year'=>'2025-2023'])['query']['where']['vod_year'] === ['in',['2023','2024','2025']], 'reversed year range is normalized');
check(request(['year'=>'2024,2025'])['query']['where']['vod_year'] === ['in',['2024','2025']], 'comma-separated year pair remains compatible');
foreach (['nonsense', '2024x2025', ['2024'], '1800-1801'] as $invalid) {
    check(!isset(request(['year'=>$invalid])['query']['where']['vod_year']), 'invalid year input is ignored without notices');
}
echo "ALL $checks CHECKS PASSED\n";
