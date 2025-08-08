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

namespace Motong\OAuth\Service;

use Motong\OAuth\Model\SysOAuthConfig;

/**
 * OAuth配置基础服务（用于认证）
 * 
 * 提供OAuth认证过程中需要的配置获取功能
 * 
 * @author Motong OAuth Team
 * @version 1.0.0
 */
class OAuthConfigService
{
    /**
     * 根据平台和客户端类型获取配置
     * 
     * @param string $platform 平台类型
     * @param string $clientType 客户端类型
     * @return array|null
     */
    public function getConfigByPlatform(string $platform, string $clientType): ?array
    {
        $config = SysOAuthConfig::enabled()
            ->platformAndClientType($platform, $clientType)
            ->first();
            
        if (!$config) {
            return null;
        }
        
        // 解密敏感信息
        $configArray = $config->toArray();
        $configArray['app_secret'] = $this->decryptSecret($config->app_secret);
        
        if (!empty($config->message_aeskey)) {
            $configArray['message_aeskey'] = $this->decryptSecret($config->message_aeskey);
        }
        
        return $configArray;
    }

    /**
     * 根据配置ID获取配置
     * 
     * @param int $configId 配置ID
     * @return array|null
     */
    public function getConfigById(int $configId): ?array
    {
        $config = SysOAuthConfig::enabled()->find($configId);
            
        if (!$config) {
            return null;
        }
        
        // 解密敏感信息
        $configArray = $config->toArray();
        $configArray['app_secret'] = $this->decryptSecret($config->app_secret);
        
        if (!empty($config->message_aeskey)) {
            $configArray['message_aeskey'] = $this->decryptSecret($config->message_aeskey);
        }
        
        return $configArray;
    }

    /**
     * 检查配置是否存在且启用
     * 
     * @param string $platform 平台类型
     * @param string $clientType 客户端类型
     * @return bool
     */
    public function hasEnabledConfig(string $platform, string $clientType): bool
    {
        return SysOAuthConfig::enabled()
            ->platformAndClientType($platform, $clientType)
            ->exists();
    }

    /**
     * 解密敏感信息
     * 
     * @param string $encryptedData 加密数据
     * @return string
     */
    private function decryptSecret(string $encryptedData): string
    {
        return base64_decode($encryptedData); 
    }
}