<?php
/**
 * Created by PhpStorm.
 * User: laijingming
 * Date: 2023/2/27
 * Time: 15:45
 */

namespace ajing\tools;

/**
 * @method RouteRegister  get(string $route, Callable $callback)
 * @method RouteRegister  post(string $route, Callable $callback)
 * @method RouteRegister  put(string $route, Callable $callback)
 * @method RouteRegister  delete(string $route, Callable $callback)
 * @method RouteRegister  options(string $route, Callable $callback)
 * @method RouteRegister  head(string $route, Callable $callback)
 */
class RouteRegister
{

    /**
     * 按照访问路径查找路由表
     * @var array
     */
    protected $pathList = [];

    /**
     * 按照别名查找路由表
     * @var array
     */
    protected $nameList = [];
    /**
     * The methods to dynamically pass through to the router.
     *
     * @var array
     */
    protected $passthru = [
        'get', 'post', 'put', 'patch', 'delete', 'options', 'any',
    ];

    /**
     * 路由组属性堆栈。
     * @var array
     */
    protected $groupStack = [];
    /**
     * 每次路由注册覆盖一次，取别名时用
     * @var array
     */
    protected $lastRouteKeyMethod = [];

    /**
     * Dynamically handle calls into the route registrar.
     *
     * @param  string $method
     * @param  array $parameters
     * @return \Illuminate\Routing\Route|$this
     *
     * @throws \BadMethodCallException
     */
    public function __call($method, $parameters)
    {
        if (in_array($method, $this->passthru)) {
            return $this->registerRoute($method, ...$parameters);
        }
        throw new \Exception(sprintf(
            'Method %s::%s does not exist.', static::class, $method
        ));
    }

    /**
     * Register a new route with the router.
     *
     * @param  string $method
     * @param  string $uri
     * @param  \Closure|array|string|null $action
     * @return \Illuminate\Routing\Route
     */
    protected function registerRoute($method, $uri, $action = null)
    {
        $endGroup = end($this->groupStack);
        $uri = static::formatPrefix(['prefix' => $uri], $endGroup);
        if (is_string($action) && isset($endGroup['namespace'])) {
            $action = static::formatNamespace(['namespace' => $action], $endGroup);
        }
        $method = strtoupper($method);
        $endGroup['action'] = $action;
        $this->pathList[$uri][$method] = $endGroup;//允许请求的方法=>匿名函数或者要执行的控制器方法
        $this->lastRouteKeyMethod = [
            'method' => $method,
            'uri' => $uri,
        ];
        return $this;
    }

    /**
     * 创建具有共享属性的路由组。
     * @param  array $attributes
     * @param  \Closure|string $routes
     * @return void
     */
    public function group(array $attributes, $routes)
    {
        if (!empty($this->groupStack)) {
            $old = end($this->groupStack);
            $attributes = array_merge($attributes, [
                'namespace' => static::formatNamespace($attributes, $old),
                'prefix' => static::formatPrefix($attributes, $old),
            ]);
            if (isset($old['middleware'])) {
                $attributes['middleware'] = array_merge_recursive($attributes['middleware'] ?? [], $old['middleware']);
            }
        }
        $this->groupStack[] = $attributes;
        if ($routes instanceof \Closure) {
            $routes($this);
        } else {
            require $routes;
        }
        array_pop($this->groupStack);
    }

    /**
     * Format the namespace for the new group attributes.
     *
     * @param  array $new
     * @param  array $old
     * @return string|null
     */
    protected static function formatNamespace($new, $old)
    {
        if (isset($new['namespace'])) {
            return isset($old['namespace'])
                ? trim($old['namespace'], '\\') . '\\' . trim($new['namespace'], '\\')
                : trim($new['namespace'], '\\');
        }
        return $old['namespace'] ?? null;
    }

    /**
     * Format the prefix for the new group attributes.
     *
     * @param  array $new
     * @param  array $old
     * @return string|null
     */
    protected static function formatPrefix($new, $old)
    {
        $old = $old['prefix'] ?? null;

        return isset($new['prefix']) ? trim($old, '/') . '/' . trim($new['prefix'], '/') : $old;
    }

    /**
     * 给路由设置别名
     * @param string $name
     */
    public function name(string $name)
    {
        $this->nameList[$name] = $this->lastRouteKeyMethod;
    }

    /**
     * 输出所有注册路由
     * @return array
     */
    public function getPathList()
    {
        return $this->pathList;
    }

    /**
     * 根据uri匹配路由
     * @param $uri
     * @return array|mixed
     */
    public function getUriToRoute($uri)
    {
        return $this->pathList[$uri] ?? [];
    }

    /**
     * 输出所有注册路由
     * @return array
     */
    public function getNameList()
    {
        return $this->nameList;
    }

    public static function getInstance()
    {
        static $instance;
        if (!$instance) {
            $instance = new static;
        }
        return $instance;
    }
}