# MacCMS v10 TVBox / 影视接口

为苹果 CMS v10 增加分类筛选接口，兼容 TVBox 和影视（FongMi）。基于 [feg545/maccms10-tvbox-api](https://github.com/feg545/maccms10-tvbox-api)。

## 安装

1. 备份现有 `application/api/controller/Tvbox.php`。
2. 将本仓库的 `Tvbox.php` 放入该目录。
3. 访问 `http://你的站点/api.php/tvbox`，确认返回 JSON 后，将该地址填入站点配置的 `api`，使用 `type: 1`。

控制器代码使用 PHP 7.4 及以上语法。MacCMS 本身支持的 PHP 环境以所用版本为准。

## 本 fork 的修复

- 保留分类 ID 作为数组键，修复分类名称及分类扩展设置错位。
- 解析影视的 `f` JSON 筛选，并继续支持 TVBox 的平铺参数。
- 精简列表返回封面和年份，避免影视补全封面时过滤掉搜索结果。
- 显式条件优先于默认筛选，搜索词只进行一次 URL 解码。
- 排序字段使用白名单；忽略数组形式的筛选值和无效年份。
- 合并播放线路过滤，保证名称、地址和线路元数据顺序一致，兼容 PHP 7.4。
- 对 XML 文本和 CDATA 做转义，处理未知分类、未知播放器和缺失线路元数据。

## 筛选参数

TVBox 平铺参数示例：

```text
/api.php/tvbox?ac=detail&t=24&pg=1&year=2024&isend=0
```

影视等价请求，以下 JSON 在实际 URL 中需要编码：

```text
/api.php/tvbox?ac=detail&t=24&pg=1&f={"year":"2024","isend":"0"}
```

分类 ID 由站点自身决定，`24` 仅为示例。

| 参数 | 含义 |
| --- | --- |
| `t` | 一级分类 ID |
| `st` | 子分类 ID，优先于 `t` |
| `year` | 四位年份，或 `2023-2025` / `2023,2025` 范围 |
| `area` | 地区关键词 |
| `h` | 最近更新小时数 |
| `isend` | `1` 已完结，`0` 未完结；空值不限制 |
| `order_by` | `vod_douban_score`、`vod_hits`、`vod_pubdate`、`vod_year`、`vod_time` |

`f` 接收 `st`、`year`、`area`、`h`、`isend`、`order_by`。同名平铺参数优先，包括显式空值；非标量筛选值被忽略。无效 JSON 不导入条件，未知排序字段回退到更新时间倒序。

未筛选的分类可使用 MacCMS 后台“系统 → 网站参数 → 预留参数 → 自定义参数”设置默认值：

```text
tvbox_default_year$$$2023
tvbox_default_area$$$中国
```

显式 `year` / `area` 不会被默认值覆盖；`f={}` 表示清除默认筛选。缓存键包含最终生效的默认值，本版使用独立的 `api_tvbox_compat_v2_` 前缀。

## 检查与维护

```sh
php -l Tvbox.php
php tests/compat.php
```

测试无需数据库或网络，通过桩模型检查公共入口的查询参数、分类映射、搜索字段、线路对应关系及 XML 输出。CI 在 PHP 7.4、8.1、8.5 上运行相同检查。

详细的审查结果和后续优化建议见 [代码审查记录](docs/REVIEW.md)。

## 筛选界面

![屏幕截图](screenshot.png)
