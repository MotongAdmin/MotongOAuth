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

use Motong\OAuth\Constants\OAuthPlatformConstants;
use Motong\OAuth\Constants\OAuthClientTypeConstants;

/**
 * 微信小程序OAuth提供者
 * 
 * @author Motong OAuth Team
 * @version 1.0.0
 */
class WeChatMiniappProvider extends AbstractOAuthProvider
{
    /**
     * 微信小程序API基础URL
     */
    const API_BASE_URL = 'https://api.weixin.qq.com';

    /**
     * jscode2session接口URL
     */
    const JSCODE2SESSION_URL = self::API_BASE_URL . '/sns/jscode2session';

    /**
     * 构造函数
     * 
     * @param array $config
     * @throws \Exception
     */
    public function __construct(array $config)
    {
        parent::__construct($config);
        
        // 验证必需的配置
        $this->validateConfig(['app_id', 'app_secret']);
        
        // 验证平台类型
        if ($this->platform !== OAuthPlatformConstants::WECHAT_MINIAPP) {
            throw new \Exception('平台类型不匹配，期望: ' . OAuthPlatformConstants::WECHAT_MINIAPP);
        }
    }

    /**
     * 获取授权URL
     * 微信小程序不需要网页授权URL，此方法主要用于兼容接口
     * 
     * @param string $state
     * @param array $options
     * @return string
     */
    public function getAuthUrl(string $state, array $options = []): string
    {
        $this->log('info', '微信小程序不需要授权URL，请在小程序端调用wx.login()获取code');
        return '';
    }

    /**
     * 通过小程序登录码获取用户信息
     * 
     * @param string $code 小程序登录码
     * @return array
     * @throws \Exception
     */
    public function getUserByCode(string $code): array
    {
        $this->log('info', '开始通过code获取微信小程序用户信息', ['code' => substr($code, 0, 10) . '...']);

        // 第一步：通过code换取session_key和openid
        $sessionData = $this->getSessionByCode($code);
        
        if (isset($sessionData['errcode']) && $sessionData['errcode'] !== 0) {
            $errorMsg = $sessionData['errmsg'] ?? '未知错误';
            $this->log('error', '微信小程序登录失败', $sessionData);
            throw new \Exception("微信小程序登录失败: {$errorMsg}");
        }

        if (empty($sessionData['openid'])) {
            $this->log('error', '微信小程序返回数据中缺少openid', $sessionData);
            throw new \Exception('微信小程序返回数据中缺少openid');
        }

        // 构建用户信息数组
        $userInfo = [
            'openid' => $sessionData['openid'],
            'unionid' => $sessionData['unionid'] ?? null,
            'session_key' => $sessionData['session_key'] ?? '',
            'platform' => $this->platform,
            'client_type' => $this->clientType,
            // 小程序基础信息（昵称、头像需要通过getUserProfile单独获取）
            'nickname' => '',
            'avatar' => '',
            'gender' => 0,
            'country' => '',
            'province' => '',
            'city' => '',
            // 原始数据
            'raw_data' => $sessionData,
        ];

        $this->log('info', '微信小程序用户信息获取成功', [
            'openid' => $userInfo['openid'],
            'has_unionid' => !empty($userInfo['unionid'])
        ]);

        return $userInfo;
    }

    /**
     * 刷新访问令牌
     * 微信小程序的session_key不支持刷新，需要重新登录
     * 
     * @param string $refreshToken
     * @return array
     * @throws \Exception
     */
    public function refreshToken(string $refreshToken): array
    {
        throw new \Exception('微信小程序不支持刷新token，请重新调用wx.login()');
    }

    /**
     * 通过code获取session信息
     * 
     * @param string $code
     * @return array
     * @throws \Exception
     */
    private function getSessionByCode(string $code): array
    {
        $params = [
            'appid' => $this->getAppId(),
            'secret' => $this->getAppSecret(),
            'js_code' => $code,
            'grant_type' => 'authorization_code',
        ];

        $url = self::JSCODE2SESSION_URL . '?' . $this->buildQuery($params);

        $this->log('info', '调用微信jscode2session接口', [
            'url' => $url,
            'appid' => $this->getAppId()
        ]);

        return $this->httpGet($url);
    }

    /**
     * 验证用户信息签名
     * 
     * @param array $userInfo 用户信息
     * @param string $signature 签名
     * @param string $sessionKey session_key
     * @return bool
     */
    public function validateUserInfoSignature(array $userInfo, string $signature, string $sessionKey): bool
    {
        $rawData = json_encode($userInfo, JSON_UNESCAPED_UNICODE);
        $expectedSignature = sha1($rawData . $sessionKey);
        
        $isValid = $signature === $expectedSignature;
        
        $this->log('info', '验证用户信息签名', [
            'is_valid' => $isValid,
            'provided_signature' => $signature,
            'expected_signature' => $expectedSignature
        ]);

        return $isValid;
    }

    /**
     * 解密用户敏感数据
     * 
     * @param string $encryptedData 加密数据
     * @param string $iv 初始向量
     * @param string $sessionKey session_key
     * @return array
     * @throws \Exception
     */
    public function decryptData(string $encryptedData, string $iv, string $sessionKey): array
    {
        $aesKey = base64_decode($sessionKey);
        $aesIV = base64_decode($iv);
        $aesCipher = base64_decode($encryptedData);

        $result = openssl_decrypt($aesCipher, 'AES-128-CBC', $aesKey, OPENSSL_RAW_DATA, $aesIV);

        if ($result === false) {
            $this->log('error', '解密用户数据失败', [
                'encrypted_data' => substr($encryptedData, 0, 20) . '...',
                'iv' => $iv
            ]);
            throw new \Exception('解密用户数据失败');
        }

        $decryptedData = json_decode($result, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->log('error', '解密后的数据格式错误', ['data' => $result]);
            throw new \Exception('解密后的数据格式错误');
        }

        $this->log('info', '用户数据解密成功');

        return $decryptedData;
    }

    /**
     * 更新用户详细信息
     * 
     * @param array $baseUserInfo 基础用户信息
     * @param array $detailUserInfo 详细用户信息（通过getUserProfile获取）
     * @return array
     */
    public function updateUserInfoWithDetails(array $baseUserInfo, array $detailUserInfo): array
    {
        // 合并用户信息
        $updatedUserInfo = array_merge($baseUserInfo, [
            'nickname' => $detailUserInfo['nickName'] ?? '',
            'avatar' => $detailUserInfo['avatarUrl'] ?? '',
            'gender' => $detailUserInfo['gender'] ?? 0,
            'country' => $detailUserInfo['country'] ?? '',
            'province' => $detailUserInfo['province'] ?? '',
            'city' => $detailUserInfo['city'] ?? '',
            'language' => $detailUserInfo['language'] ?? '',
        ]);

        $this->log('info', '用户详细信息更新完成', [
            'has_nickname' => !empty($updatedUserInfo['nickname']),
            'has_avatar' => !empty($updatedUserInfo['avatar'])
        ]);

        return $updatedUserInfo;
    }

    /**
     * 检查session_key是否有效
     * 
     * @param string $sessionKey
     * @param string $signature
     * @param string $rawData
     * @return bool
     */
    public function checkSessionKey(string $sessionKey, string $signature, string $rawData): bool
    {
        $expectedSignature = sha1($rawData . $sessionKey);
        return $signature === $expectedSignature;
    }

    /**
     * 获取微信小程序访问令牌（用于调用其他微信接口）
     * 
     * @return array
     * @throws \Exception
     */
    public function getAccessToken(): array
    {
        $params = [
            'grant_type' => 'client_credential',
            'appid' => $this->getAppId(),
            'secret' => $this->getAppSecret(),
        ];

        $url = self::API_BASE_URL . '/cgi-bin/token?' . $this->buildQuery($params);
        
        $this->log('info', '获取微信小程序访问令牌');

        $response = $this->httpGet($url);

        if (isset($response['errcode']) && $response['errcode'] !== 0) {
            $errorMsg = $response['errmsg'] ?? '未知错误';
            $this->log('error', '获取访问令牌失败', $response);
            throw new \Exception("获取访问令牌失败: {$errorMsg}");
        }

        return $response;
    }

    /**
     * 获取平台特定的错误码说明
     * 
     * @param int $errcode
     * @return string
     */
    public function getErrorMessage(int $errcode): string
    {
        $errorMessages = [
            -1 => '系统繁忙，此时请开发者稍候再试',
            0 => '请求成功',
            40013 => 'invalid appid',
            40125 => 'invalid appsecret',
            40163 => 'code been used',
            40029 => 'invalid code',
            45011 => 'api minute-quota reach limit',
            // 更多错误码...
        ];

        return $errorMessages[$errcode] ?? "未知错误码: {$errcode}";
    }
}