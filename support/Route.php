<?php

namespace ajing\support;

/**
 * @method static \ajing\route\Route get(string $uri, \Closure|array|string|null $action = null)
 * @method static \ajing\route\Route post(string $uri, \Closure|array|string|null $action = null)
 * @method static \ajing\route\Route put(string $uri, \Closure|array|string|null $action = null)
 * @method static \ajing\route\Route delete(string $uri, \Closure|array|string|null $action = null)
 * @method static \ajing\route\Route patch(string $uri, \Closure|array|string|null $action = null)
 * @method static \ajing\route\Route options(string $uri, \Closure|array|string|null $action = null)
 * @method static \ajing\route\Route any(string $uri, \Closure|array|string|null $action = null)
 * @method static \ajing\route\Route match(array|string $methods, string $uri, \Closure|array|string|null $action = null)
 * @method static \ajing\route\Route prefix(string $prefix)
 * @method static \ajing\route\Route middleware(array|string|null $middleware)
 * @method static \ajing\route\Route as(string $value)
 * @method static \ajing\route\Route domain(string $value)
 * @method static \ajing\route\Route name(string $value)
 * @method static \ajing\route\Route namespace(string $value)
 * @method static \ajing\route\Route group(array|\Closure|string $attributes, \Closure|string $routes)
 * @see \ajing\route\Route
 */
class Route extends Facade
{
    /**
     * Get the registered name of the component.
     *
     * @return string
     */
    protected static function getFacadeAccessor()
    {
        return \ajing\route\Route::class;
    }

    /**
     * 获取注册组键实例
     * @throws \Exception
     */
    protected static function createInstance()
    {
        return new \ajing\route\Route();
    }
}
