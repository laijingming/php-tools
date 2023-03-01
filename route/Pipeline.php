<?php
/**
 * Created by PhpStorm.
 * User: aj407
 * Date: 2023/2/25
 * Time: 13:20
 */

namespace ajing\route;


class Pipeline {


    /**
     * 类管道数组
     * @var array
     */
    protected $pipes = [];

    /**
     * 要在每个管道上调用的方法
     * @var string
     */
    protected $method = 'handle';
    /**
     * 容器实现
     * @var \lib\Ioc
     */
    protected static $container;
    /**
     * 创建实例
     * Pipeline constructor.
     * @param Ioc|null $container
     */
    public function __construct(Ioc $container = null) {
        if (!self::$container){
            self::$container = $container;
        }
    }

    /**
     * 设置管道数组
     * @param  array|mixed $pipes
     * @return $this
     */
    public function through($pipes) {
        $this->pipes = is_array($pipes) ? $pipes : func_get_args();
        return $this;
    }

    /**
     * 设置管道要执行的方法
     * @param  string $method
     * @return $this
     */
    public function via($method) {
        $this->method = $method;
        return $this;
    }

    /**
     * 使用最终目标回调运行管道
     * @param  \Closure $destination
     * @return mixed
     */
    public function then(\Closure $destination) {
        $pipeline = array_reduce(
            array_reverse($this->pipes), $this->carry(), $this->prepareDestination($destination)
        );

        return $pipeline();
    }

    /**
     * 完成收尾执行的最后一部分闭包函数
     * @param \Closure $destination
     * @return \Closure
     */
    protected function prepareDestination(\Closure $destination) {
        return function () use ($destination) {
            return $destination();
        };
    }

    /**
     * Get a Closure that represents a slice of the application onion.
     * $stack 匿名函数
     * $pipe 中间件类名数组
     * @return \Closure
     */
    protected function carry() {
        return function ($stack, $pipe) {
            return function () use ($stack, $pipe) {
                if (is_callable($pipe)) {
                    // 如果管道是 Closure 的一个实例，我们将直接调用它，但
                    // 否则我们将从容器中解析管道并使用适当的方法和参数调用它，将结果返回。
                    return $pipe( $stack);
                } elseif (!is_object($pipe)) {
                    [$name, $parameters] = $this->parsePipeString($pipe);

                    // 如果管道是一个字符串，我们将解析该字符串并将类从依赖注入容器中解析出来。然后我们可以构建一个可调用对象并执行提供所需参数的管道函数。
                    $pipe = $this->getContainer()->make($name);

                    $parameters = array_merge([$stack], $parameters);
                } else {
                    // 如果管道已经是一个对象，我们将创建一个可调用对象并将其按原样传递给
                    // 管道。不需要做任何额外的解析和格式化
                    // 因为我们得到的对象已经是一个完全实例化的对象。
                    $parameters = [$stack];
                }

                $response = method_exists($pipe, $this->method)
                    ? $pipe->{$this->method}(...$parameters)
                    : $pipe(...$parameters);

                return $response;
            };
        };
    }

    /**
     * 分析完整管道字符串以获取名称和参数。
     * @param  string $pipe
     * @return array
     */
    protected function parsePipeString($pipe) {
        [$name, $parameters] = array_pad(explode(':', $pipe, 2), 2, []);

        if (is_string($parameters)) {
            $parameters = explode(',', $parameters);
        }

        return [$name, $parameters];
    }

    /**
     * 获得容器实例
     * @return object Ioc|null
     * @throws \Exception
     */
    protected function getContainer() {
        if (!self::$container) {
            throw new \Exception('A container instance has not been passed to the Pipeline.');
        }

        return self::$container;
    }
}
