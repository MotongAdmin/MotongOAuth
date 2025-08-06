<?php

declare(strict_types=1);

namespace Motong\OAuth\Constants;

/**
 * OAuth客户端类型常量
 * 
 * @author Motong OAuth Team
 * @version 1.0.0
 */
class OAuthClientTypeConstants
{
    const WEB = 'web';                 // 网页端(PC/Mobile Web)
    const MINIAPP = 'miniapp';         // 小程序端(微信/支付宝小程序)
    const APP = 'app';                 // 移动应用(iOS/Android App)
    const DESKTOP = 'desktop';         // 桌面应用(Windows/Mac/Linux)

    /**
     * 获取所有客户端类型
     * 
     * @return array
     */
    public static function getAllClientTypes(): array
    {
        return [
            self::WEB => '网页端',
            self::MINIAPP => '小程序',
            self::APP => '移动应用',
            self::DESKTOP => '桌面应用',
        ];
    }

    /**
     * 验证客户端类型是否有效
     * 
     * @param string $clientType
     * @return bool
     */
    public static function isValidClientType(string $clientType): bool
    {
        return array_key_exists($clientType, self::getAllClientTypes());
    }

    /**
     * 获取客户端类型显示名称
     * 
     * @param string $clientType
     * @return string
     */
    public static function getClientTypeName(string $clientType): string
    {
        $clientTypes = self::getAllClientTypes();
        return $clientTypes[$clientType] ?? '未知客户端';
    }

    /**
     * 获取移动端客户端类型
     * 
     * @return array
     */
    public static function getMobileClientTypes(): array
    {
        return [
            self::MINIAPP => '小程序',
            self::APP => '移动应用',
        ];
    }

    /**
     * 获取PC端客户端类型
     * 
     * @return array
     */
    public static function getDesktopClientTypes(): array
    {
        return [
            self::WEB => '网页端',
            self::DESKTOP => '桌面应用',
        ];
    }

    /**
     * 判断是否为移动端客户端类型
     * 
     * @param string $clientType
     * @return bool
     */
    public static function isMobileClientType(string $clientType): bool
    {
        return in_array($clientType, [self::MINIAPP, self::APP]);
    }

    /**
     * 判断是否为桌面端客户端类型
     * 
     * @param string $clientType
     * @return bool
     */
    public static function isDesktopClientType(string $clientType): bool
    {
        return in_array($clientType, [self::WEB, self::DESKTOP]);
    }
}