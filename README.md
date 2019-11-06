# lazy-curd
支持快速创建增删改查方法和测试用例

## 安装 - install
* composer 安装
```bash
$ composer require 96qbhy/lazy-curd
```

## 使用 - usage
```bash
$ php artisan lazy:make App\\Models\\Media
```
> 支持 namespace(默认 App\Http\Controllers) 和 excludes(需要排除的字段，默认 id,created_at,updated_at) 可选项

## 关于 - about
author: 96qbhy@gmail.com