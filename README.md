## 注意事项

- php >= 8.1
- 如果使用注释给 getter 设置返回值，返回 `Illuminate\\Database\\Eloquent\\Casts\\Attribute` 类，不能简写为 `Attribute`
- 如果使用 `Illuminate\\Database\\Eloquent\\Casts\\Attribute` 作为返回值，需要给 `get` 也写上返回值，比如 `Attribute::get(fn(): string => 'hello')`，否则将会生成 `any`

## 食用方式

```shell
# install
composer require niefufeng/laravel-model-typescript --dev
# publish config files
php artisan vendor:publish --tag=model-typescript
# generate
php artisan model-typescript:generate
```