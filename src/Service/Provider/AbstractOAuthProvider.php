<?php
/**
 * This file is part of Motong-Admin.
 *
 * @link     https://github.com/MotongAdmin
 * @document https://github.com/MotongAdmin
 * @contact  1003081775@qq.com
 * @author   zyvincent 
 * @Company  Icodefuture Information Technology Co., Ltd.
 * @license  GPL
 */
declare(strict_types=1);

namespace Motong\OAuth\Service\Provider;
use ZYProSoft\Log\Log;

/**
 * OAuth提供者抽象基类
 * 
 * @author Motong OAuth Team
 * @version 1.0.0
 */
abstract class AbstractOAuthProvider
{
    /**
     * OAuth配置
     * 
     * @var array
     */
    protected array $config;

    /**
     * 平台类型
     * 
     * @var string
     */
    protected string $platform;

    /**
     * 客户端类型
     * 
     * @var string
     */
    protected string $clientType;

    /**
     * 构造函数
     * 
     * @param array $config OAuth配置
     */
    public function __construct(array $config)
    {
        $this->config = $config;
        $this->platform = $config['platform'] ?? '';
        $this->clientType = $config['client_type'] ?? '';
    }

    /**
     * 获取授权URL
     * 
     * @param string $state 状态参数
     * @param array $options 额外选项
     * @return string
     */
    abstract public function getAuthUrl(string $state, array $options = []): string;

    /**
     * 通过授权码获取用户信息
     * 
     * @param string $code 授权码
     * @return array
     * @throws \Exception
     */
    abstract public function getUserByCode(string $code): array;

    /**
     * 刷新访问令牌
     * 
     * @param string $refreshToken 刷新令牌
     * @return array
     * @throws \Exception
     */
    abstract public function refreshToken(string $refreshToken): array;

    /**
     * 获取平台类型
     * 
     * @return string
     */
    public function getPlatform(): string
    {
        return $this->platform;
    }

    /**
     * 获取客户端类型
     * 
     * @return string
     */
    public function getClientType(): string
    {
        return $this->clientType;
    }

    /**
     * 获取配置项
     * 
     * @param string $key 配置键
     * @param mixed $default 默认值
     * @return mixed
     */
    protected function getConfig(string $key, $default = null)
    {
        return $this->config[$key] ?? $default;
    }

    /**
     * 获取AppID
     * 
     * @return string
     */
    protected function getAppId(): string
    {
        return $this->getConfig('app_id', '');
    }

    /**
     * 获取AppSecret
     * 
     * @return string
     */
    protected function getAppSecret(): string
    {
        return $this->getConfig('app_secret', '');
    }

    /**
     * 获取回调地址
     * 
     * @return string
     */
    protected function getRedirectUri(): string
    {
        return $this->getConfig('auth_redirect', '');
    }

    /**
     * 获取授权范围
     * 
     * @return string
     */
    protected function getScopes(): string
    {
        return $this->getConfig('scopes', 'snsapi_userinfo');
    }

    /**
     * 构建HTTP查询字符串
     * 
     * @param array $params 参数数组
     * @return string
     */
    protected function buildQuery(array $params): string
    {
        return http_build_query($params, '', '&', PHP_QUERY_RFC3986);
    }

    /**
     * 发送HTTP GET请求
     * 
     * @param string $url 请求URL
     * @param array $headers 请求头
     * @return array
     * @throws \Exception
     */
    protected function httpGet(string $url, array $headers = []): array
    {
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_USERAGENT => 'Motong-OAuth/1.0',
        ]);

        if (!empty($headers)) {
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        }

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($response === false || !empty($error)) {
            throw new \Exception("HTTP请求失败: {$error}");
        }

        if ($httpCode !== 200) {
            throw new \Exception("HTTP请求失败: HTTP {$httpCode}");
        }

        $data = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \Exception("响应数据格式错误: " . json_last_error_msg());
        }

        return $data;
    }

    /**
     * 发送HTTP POST请求
     * 
     * @param string $url 请求URL
     * @param array $data 请求数据
     * @param array $headers 请求头
     * @return array
     * @throws \Exception
     */
    protected function httpPost(string $url, array $data = [], array $headers = []): array
    {
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($data),
            CURLOPT_TIMEOUT => 30,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_USERAGENT => 'Motong-OAuth/1.0',
        ]);

        $defaultHeaders = ['Content-Type: application/json'];
        $headers = array_merge($defaultHeaders, $headers);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($response === false || !empty($error)) {
            throw new \Exception("HTTP请求失败: {$error}");
        }

        if ($httpCode !== 200) {
            throw new \Exception("HTTP请求失败: HTTP {$httpCode}");
        }

        $data = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \Exception("响应数据格式错误: " . json_last_error_msg());
        }

        return $data;
    }

    /**
     * 验证必需的配置项
     * 
     * @param array $required 必需的配置键
     * @throws \Exception
     */
    protected function validateConfig(array $required): void
    {
        foreach ($required as $key) {
            if (empty($this->getConfig($key))) {
                throw new \Exception("缺少必需的配置: {$key}");
            }
        }
    }

    /**
     * 记录日志
     * 
     * @param string $level 日志级别
     * @param string $message 日志消息
     * @param array $context 上下文数据
     */
    protected function log(string $level, string $message, array $context = []): void
    {
        Log::log($message, $level, 'default');
    }
}