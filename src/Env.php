<?php
/**
 * Created by PhpStorm.
 * User: laijingming
 * Date: 2023/2/16
 * Time: 15:29
 */

namespace ajing;

class Env
{
    const ENV_PREFIX = 'PHP_';

    /**
     * .env文件路径
     * @var string
     */
    protected $filePath;

    /**
     * 是否已经加载
     * @var bool
     */
    static $loaded = false;

    /**
     * 创建实例
     * @param string $path 文件目录地址
     * @param string $file 文件名
     *
     * @return void
     */
    function __construct($path = '', $file = '.env')
    {
        $this->filePath = $this->getFilePath($path, $file);
    }

    /**
     * 返回文件的完整路径
     * @param string $path
     * @param string $file
     *
     * @return string
     */
    protected function getFilePath($path, $file)
    {
        if (!is_string($path) || empty($path)) {
            $path = dirname(dirname(dirname(dirname(__DIR__)))) . '/';
        }
        if (!is_string($file)) {
            $file = '.env';
        }

        return rtrim($path, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $file;
    }

    /**
     * 在给定目录中加载环境文件
     * @param bool $isLoadEnv 是否载入超全局环境变量
     * @throws \Exception
     * @return array
     */
    public function load($isLoadEnv = false)
    {
        $this->ensureFileIsReadable();//确保文件可读
        $lines = $this->readLinesFromFile($this->filePath);
        foreach ($lines as $line) {
            if (!$this->isComment($line) && $this->looksLikeSetter($line)) {
                //拆分复合字符串
                list($name, $value) = array_map('trim', explode('=', $line, 2));
                //从环境变量名称中去除引号和可选的前导“export”
                $name = trim(str_replace(array('export ', '\'', '"'), '', $name));
                list($name, $value) = $this->sanitiseVariableValue($name, $value);
                $value = $this->resolveNestedVariables($value);
                if ($this->getEnvironmentVariable($name) !== null) {
                    continue;
                }
                putenv(self::ENV_PREFIX . "$name=$value");
                if ($isLoadEnv) {
                    $_ENV[self::ENV_PREFIX . $name] = $value;
                    $_SERVER[self::ENV_PREFIX . $name] = $value;
                }
            }
        }
        self::$loaded = true;
        return $lines;
    }

    /**
     * 解析嵌套变量
     * @param string $value
     *
     * @return mixed
     */
    protected function resolveNestedVariables($value)
    {
        if (strpos($value, '$') !== false) {
            $loader = $this;
            $value = preg_replace_callback(
                '/\${([a-zA-Z0-9_]+)}/',
                function ($matchedPatterns) use ($loader) {
                    $nestedVariable = $loader->getEnvironmentVariable($matchedPatterns[1]);
                    if ($nestedVariable === null) {
                        return $matchedPatterns[0];
                    }
                    return $nestedVariable;
                },
                $value
            );
        }

        return $value;
    }

    /**
     * 从环境变量值中去除引号
     * @param string $name
     * @param string $value
     *
     * @throws \Exception
     *
     * @return array
     */
    protected function sanitiseVariableValue($name, $value)
    {
        $value = trim($value);
        if (!$value) {
            return array($name, $value);
        }

        if ($this->beginsWithAQuote($value)) { // value starts with a quote
            $quote = $value[0];
            $regexPattern = sprintf(
                '/^
                %1$s          # match a quote at the start of the value
                (             # capturing sub-pattern used
                 (?:          # we do not need to capture this
                  [^%1$s\\\\] # any character other than a quote or backslash
                  |\\\\\\\\   # or two backslashes together
                  |\\\\%1$s   # or an escaped quote e.g \"
                 )*           # as many characters that match the previous rules
                )             # end of the capturing sub-pattern
                %1$s          # and the closing quote
                .*$           # and discard any string after the closing quote
                /mx',
                $quote
            );
            $value = preg_replace($regexPattern, '$1', $value);
            $value = str_replace("\\$quote", $quote, $value);
            $value = str_replace('\\\\', '\\', $value);
        } else {
            $parts = explode(' #', $value, 2);
            $value = trim($parts[0]);

            // 未加引号的值不能包含空格
            if (preg_match('/\s+/', $value) > 0) {
                throw new \Exception('.env values containing spaces must be surrounded by quotes.');
            }
        }

        return array($name, trim($value));
    }

    /**
     * 确定给定的字符串是否以引号开头
     * @param string $value
     *
     * @return bool
     */
    protected function beginsWithAQuote($value)
    {
        return strpbrk($value[0], '"\'') !== false;
    }

    /**
     * 在不同的地方搜索环境变量并返回找到的第一个值
     * @param string $name
     *
     * @return string|null
     */
    public function getEnvironmentVariable($name)
    {
        switch (true) {
            case array_key_exists($name, $_ENV):
                return $_ENV[$name];
            case array_key_exists($name, $_SERVER):
                return $_SERVER[$name];
            default:
                $value = getenv($name);
                return $value === false ? null : $value; // switch getenv default to null
        }
    }

    /**
     * 确定文件中的行是否是注释，例如以#开头
     * @param string $line
     *
     * @return bool
     */
    protected function isComment($line)
    {
        return strpos(ltrim($line), '#') === 0;
    }

    /**
     * 确定给定的行是否看起来像是在设置一个变量。
     * @param string $line
     *
     * @return bool
     */
    protected function looksLikeSetter($line)
    {
        return strpos($line, '=') !== false;
    }

    /**
     * 从文件中读取行，自动检测行尾
     * @param string $filePath
     *
     * @return array
     */
    protected function readLinesFromFile($filePath)
    {
        // Read file into an array of lines with auto-detected line endings
        $autodetect = ini_get('auto_detect_line_endings');
        ini_set('auto_detect_line_endings', '1');
        $lines = file($filePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        ini_set('auto_detect_line_endings', $autodetect);

        return $lines;
    }

    /**
     * 确保给定的文件路径是可读的
     * @return void
     * @throws \Exception
     *
     */
    protected function ensureFileIsReadable()
    {
        if (!is_readable($this->filePath) || !is_file($this->filePath)) {
            throw new \Exception(sprintf('配置文件%s不存在' . PHP_EOL, $this->filePath));
        }
    }
}