<?php
/**
 * Created by PhpStorm.
 * User: laijingming
 * Date: 2023/2/21
 * Time: 15:16
 */

namespace ajing\route;


class Request
{
    protected $query = [];
    protected $body = [];
    /**
     * @var string
     */
    protected $method;
    protected $files = [];
    /**
     * 服务器和执行环境参数（$_SERVER）
     * @var ServerBag
     */
    public $server;
    /**
     * Headers (taken from the $_SERVER).
     *
     * @var HeaderBag
     */
    public $headers;
    /**
     * @var string
     */
    protected $requestUri;

    /**
     * @var string
     */
    protected $path;

    public function __construct()
    {
        $this->server = new ServerBag($_SERVER);
//        $this->headers = new HeaderBag($this->server->getHeaders());
    }

    public function path()
    {
        if (null === $this->path) {
            $this->path = parse_url($this->getRequestUri(), PHP_URL_PATH);
        }
        return $this->path;
    }

    public function getBody()
    {
        $method = $this->getMethod();
        if ($method === 'GET') {
            return [];
        } else if ($method === 'POST') {
            return $_POST;
        } else if ($method === 'PUT' || $method === 'DELETE') {
            parse_str(file_get_contents('php://input'), $body);
            return $body;
        }
        return [];
    }

    /**
     * 获取请求方法。
     * @return string
     */
    public function getMethod()
    {
        if (null !== $this->method) {
            return $this->method;
        }
        return $this->method = strtoupper($this->server->get('REQUEST_METHOD', 'GET'));
    }


    /**
     * 返回请求的 URI（路径和查询字符串）。
     * @return string 原始 URI（即未解码的 URI）
     */
    public function getRequestUri()
    {
        if (null === $this->requestUri) {
            $this->requestUri = $this->prepareRequestUri();
        }

        return $this->requestUri;
    }

    protected function prepareRequestUri()
    {
        $requestUri = '';

        if ('1' == $this->server->get('IIS_WasUrlRewritten') && '' != $this->server->get('UNENCODED_URL')) {
            //带 URL 重写的 IIS7：确保我们得到未编码的 URL（双斜线问题）
            $requestUri = $this->server->get('UNENCODED_URL');
            $this->server->remove('UNENCODED_URL');
            $this->server->remove('IIS_WasUrlRewritten');
        } elseif ($this->server->has('REQUEST_URI')) {
            $requestUri = $this->server->get('REQUEST_URI');
            if ('' !== $requestUri && '/' === $requestUri[0]) {
                // 仅使用路径和查询删除片段。
                if (false !== $pos = strpos($requestUri, '#')) {
                    $requestUri = substr($requestUri, 0, $pos);
                }
            } else {
                //HTTP代理请求设置请求URI，其中包含方案和主机[和端口]+URL路径，
                //仅使用URL路径。
                $uriComponents = parse_url($requestUri);
                if (isset($uriComponents['path'])) {
                    $requestUri = $uriComponents['path'];
                }
                if (isset($uriComponents['query'])) {
                    $requestUri .= '?' . $uriComponents['query'];
                }
            }
        } elseif ($this->server->has('ORIG_PATH_INFO')) {
            // IIS 5.0, PHP as CGI
            $requestUri = $this->server->get('ORIG_PATH_INFO');
            if ('' != $this->server->get('QUERY_STRING')) {
                $requestUri .= '?' . $this->server->get('QUERY_STRING');
            }
            $this->server->remove('ORIG_PATH_INFO');
        }

        // 规范化请求URI以方便从此请求创建子请求
        $this->server->set('REQUEST_URI', $requestUri);

        return $requestUri;
    }
}
