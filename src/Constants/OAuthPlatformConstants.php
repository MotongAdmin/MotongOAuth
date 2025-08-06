<?php

declare(strict_types=1);

namespace Motong\OAuth\Constants;

/**
 * OAuth第三方平台类型常量
 * 
 * @author Motong OAuth Team
 * @version 1.0.0
 */
class OAuthPlatformConstants
{
    // 微信系列平台
    const WECHAT_MP = 'wechat_mp';              // 微信公众号网页授权
    const WECHAT_MINIAPP = 'wechat_miniapp';    // 微信小程序登录
    const WECHAT_OPEN = 'wechat_open';          // 微信开放平台App登录
    const WEWORK = 'wework';                    // 企业微信登录

    // 支付宝系列平台
    const ALIPAY_WEB = 'alipay_web';            // 支付宝网页授权
    const ALIPAY_MINIAPP = 'alipay_miniapp';    // 支付宝小程序登录
    const ALIPAY_APP = 'alipay_app';            // 支付宝App登录

    // 腾讯QQ系列
    const QQ_WEB = 'qq_web';                    // QQ互联网页登录
    const QQ_APP = 'qq_app';                    // QQ开放平台App登录

    // 其他主流平台
    const WEIBO = 'weibo';                      // 新浪微博登录
    const DINGTALK = 'dingtalk';               // 钉钉登录
    const BAIDU = 'baidu';                     // 百度登录
    const BYTEDANCE = 'bytedance';             // 抖音/字节跳动登录

    // 开发者平台(适用于技术类产品)
    const GITHUB = 'github';                   // GitHub登录
    const GITLAB = 'gitlab';                   // GitLab登录
    const GITEE = 'gitee';                     // Gitee码云登录

    // 国外平台(国际化项目使用)
    const GOOGLE = 'google';                   // Google登录
    const FACEBOOK = 'facebook';               // Facebook登录
    const APPLE = 'apple';                     // Apple Sign In
    const MICROSOFT = 'microsoft';             // Microsoft登录
    const LINKEDIN = 'linkedin';               // LinkedIn登录
    const TWITTER = 'twitter';                 // Twitter登录

    /**
     * 获取所有支持的平台列表
     * 
     * @return array
     */
    public static function getAllPlatforms(): array
    {
        return [
            // 微信系列
            self::WECHAT_MP => '微信公众号',
            self::WECHAT_MINIAPP => '微信小程序',
            self::WECHAT_OPEN => '微信开放平台',
            self::WEWORK => '企业微信',

            // 支付宝系列
            self::ALIPAY_WEB => '支付宝网页版',
            self::ALIPAY_MINIAPP => '支付宝小程序',
            self::ALIPAY_APP => '支付宝App',

            // 腾讯QQ系列
            self::QQ_WEB => 'QQ互联',
            self::QQ_APP => 'QQ移动应用',

            // 其他国内平台
            self::WEIBO => '新浪微博',
            self::DINGTALK => '钉钉',
            self::BAIDU => '百度',
            self::BYTEDANCE => '字节跳动',

            // 开发者平台
            self::GITHUB => 'GitHub',
            self::GITLAB => 'GitLab',
            self::GITEE => 'Gitee码云',

            // 国外平台
            self::GOOGLE => 'Google',
            self::FACEBOOK => 'Facebook',
            self::APPLE => 'Apple',
            self::MICROSOFT => 'Microsoft',
            self::LINKEDIN => 'LinkedIn',
            self::TWITTER => 'Twitter',
        ];
    }

    /**
     * 获取国内常用平台(第一期重点支持)
     * 
     * @return array
     */
    public static function getDomesticPlatforms(): array
    {
        return [
            self::WECHAT_MP => '微信公众号',
            self::WECHAT_MINIAPP => '微信小程序',
            self::WECHAT_OPEN => '微信开放平台',
            self::ALIPAY_WEB => '支付宝网页版',
            self::ALIPAY_MINIAPP => '支付宝小程序',
            self::QQ_WEB => 'QQ互联',
            self::WEIBO => '新浪微博',
        ];
    }

    /**
     * 获取开发者平台(技术类产品适用)
     * 
     * @return array
     */
    public static function getDeveloperPlatforms(): array
    {
        return [
            self::GITHUB => 'GitHub',
            self::GITLAB => 'GitLab',
            self::GITEE => 'Gitee码云',
        ];
    }

    /**
     * 验证平台类型是否有效
     * 
     * @param string $platform
     * @return bool
     */
    public static function isValidPlatform(string $platform): bool
    {
        return array_key_exists($platform, self::getAllPlatforms());
    }

    /**
     * 获取平台显示名称
     * 
     * @param string $platform
     * @return string
     */
    public static function getPlatformName(string $platform): string
    {
        $platforms = self::getAllPlatforms();
        return $platforms[$platform] ?? '未知平台';
    }

    /**
     * 根据产品类型获取推荐的登录平台
     * 
     * @param string $productType
     * @return array
     */
    public static function getRecommendedPlatforms(string $productType): array
    {
        switch ($productType) {
            case 'ecommerce': // 电商类
                return [self::WECHAT_MP, self::WECHAT_MINIAPP, self::ALIPAY_WEB];
                
            case 'social': // 社交类
                return [self::WECHAT_MP, self::QQ_WEB, self::WEIBO];
                
            case 'enterprise': // 企业类
                return [self::WECHAT_MP, self::DINGTALK, self::WEWORK];
                
            case 'developer': // 开发者工具
                return [self::GITHUB, self::GITLAB, self::GITEE];
                
            case 'universal': // 通用型
                return [self::WECHAT_MP, self::WECHAT_MINIAPP, self::ALIPAY_WEB, self::QQ_WEB];
                
            default:
                return [self::WECHAT_MP]; // 默认支持微信
        }
    }
}