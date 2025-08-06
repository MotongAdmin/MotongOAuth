<?php

declare(strict_types=1);

namespace Motong\OAuth\Controller\Common;

use Motong\OAuth\Service\OAuthAuthService;
use Motong\OAuth\Constants\OAuthPlatformConstants;
use Motong\OAuth\Constants\OAuthClientTypeConstants;
use App\Annotation\Description;
use ZYProSoft\Controller\AbstractController;
use Hyperf\HttpServer\Annotation\AutoController;
use Hyperf\Di\Annotation\Inject;
use ZYProSoft\Http\AuthedRequest;

/**
 * OAuth认证控制器 - 公共模块
 * @AutoController(prefix="/motong/oauth")
 * 
 * 处理用户的OAuth登录、绑定、解绑等操作
 * 
 * @author Motong OAuth Team
 * @version 1.0.0
 */
class OAuthController extends AbstractController
{
    /**
     * @Inject
     * @var OAuthAuthService
     */
    protected OAuthAuthService $oauthService;

    /**
     * 自定义验证错误消息
     * @return array
     */
    public function messages()
    {
        return [
            // 通用规则消息
            'required' => ':attribute不能为空',
            'string' => ':attribute必须是字符串',
            'integer' => ':attribute必须是整数',
            'url' => ':attribute必须是有效的URL',
            'in' => ':attribute的值不在允许范围内',
            
            // 字段特定消息
            'platform.required' => '平台类型不能为空',
            'client_type.required' => '客户端类型不能为空',
            'action.required' => '操作类型不能为空',
            'action.in' => '操作类型只能是login或bind',
            'code.required' => '授权码不能为空',
            'state.required' => 'state参数不能为空',
            'redirect_url.url' => '跳转地址必须是有效的URL',
            'user_id.integer' => '用户ID必须是整数',
            'bind_id.integer' => '绑定ID必须是整数',
        ];
    }

    /**
     * @Description("获取OAuth授权URL")
     * ZGW接口名: common.oauth.getAuthUrl
     * 获取第三方平台的OAuth授权URL
     */
    public function getAuthUrl()
    {
        // 验证参数
        $this->validate([
            'platform' => 'required|string',
            'client_type' => 'required|string',
            'action' => 'required|string|in:login,bind',
            'redirect_url' => 'string|url',
            'user_id' => 'integer', // 绑定操作时必需
        ]);

        $platform = $this->request->param('platform');
        $clientType = $this->request->param('client_type');
        $action = $this->request->param('action');
        $redirectUrl = $this->request->param('redirect_url');
        $userId = $this->request->param('user_id');

        // 验证平台和客户端类型
        if (!OAuthPlatformConstants::isValidPlatform($platform)) {
            throw new \ZYProSoft\Exception\HyperfCommonException(\App\Constants\ErrorCode::PARAM_ERROR, '不支持的OAuth平台');
        }

        if (!OAuthClientTypeConstants::isValidClientType($clientType)) {
            throw new \ZYProSoft\Exception\HyperfCommonException(\App\Constants\ErrorCode::PARAM_ERROR, '不支持的客户端类型');
        }

        // 绑定操作需要提供用户ID
        if ($action === 'bind' && empty($userId)) {
            throw new \ZYProSoft\Exception\HyperfCommonException(\App\Constants\ErrorCode::PARAM_ERROR, '绑定操作必须提供用户ID');
        }

        $result = $this->oauthService->getAuthUrl($platform, $clientType, $action, $userId, $redirectUrl);
        
        return $this->success($result);
    }

    /**
     * @Description("OAuth授权回调登录")
     * ZGW接口名: motong.oauth.loginByCode
     * 通过OAuth授权码进行登录
     */
    public function loginByCode()
    {
        // 验证参数
        $this->validate([
            'platform' => 'required|string',
            'code' => 'required|string',
            'state' => 'string', // 某些平台（如微信小程序）state可选
            'client_type' => 'string', // 客户端类型（当state为空时可用）
        ]);

        $platform = $this->request->param('platform');
        $code = $this->request->param('code');
        $state = $this->request->param('state', '');
        $clientType = $this->request->param('client_type', '');

        $result = $this->oauthService->loginByCode($platform, $code, $state, $clientType);
        
        return $this->success($result);
    }

    /**
     * @Description("绑定第三方账号")
     * ZGW接口名: common.oauth.bindAccount
     * 为已登录用户绑定第三方账号
     */
    public function bindAccount(AuthedRequest $request)
    {
        // 验证参数
        $this->validate([
            'platform' => 'required|string',
            'code' => 'required|string',
            'state' => 'required|string',
        ]);

        $userId = $this->getUserId();
        $platform = $request->param('platform');
        $code = $request->param('code');
        $state = $request->param('state');

        $result = $this->oauthService->bindAccount($userId, $platform, $code, $state);
        
        return $this->success($result);
    }

    /**
     * @Description("解除第三方账号绑定")
     * ZGW接口名: motong.oauth.unbindAccount
     * 解除用户与第三方账号的绑定关系
     */
    public function unbindAccount(AuthedRequest $request)
    {
        // 验证参数
        $this->validate([
            'platform' => 'required|string',
        ]);

        $userId = $this->getUserId();
        $platform = $request->param('platform');

        $this->oauthService->unbindAccount($userId, $platform);
        
        return $this->success([]);
    }

    /**
     * @Description("获取用户绑定的第三方账号列表")
     * ZGW接口名: motong.oauth.getBindList
     * 获取当前用户已绑定的第三方账号列表
     */
    public function getBindList(AuthedRequest $request)
    {
        $userId = $this->getUserId();
        $bindList = $this->oauthService->getBindList($userId);
        
        return $this->success([
            'bind_list' => $bindList,
            'supported_platforms' => OAuthPlatformConstants::getDomesticPlatforms(),
        ]);
    }

    /**
     * @Description("刷新访问令牌")
     * ZGW接口名: motong.oauth.refreshToken
     * 刷新第三方平台的访问令牌
     */
    public function refreshToken(AuthedRequest $request)
    {
        // 验证参数
        $this->validate([
            'bind_id' => 'required|integer',
        ]);

        $userId = $this->getUserId();
        $bindId = $request->param('bind_id');

        $result = $this->oauthService->refreshAccessToken($bindId);
        
        return $this->success($result);
    }

    /**
     * @Description("获取支持的OAuth平台列表")
     * ZGW接口名: motong.oauth.getSupportedPlatforms
     * 获取系统支持的OAuth平台和客户端类型
     */
    public function getSupportedPlatforms()
    {
        $platforms = [
            'all_platforms' => OAuthPlatformConstants::getAllPlatforms(),
            'domestic_platforms' => OAuthPlatformConstants::getDomesticPlatforms(),
            'developer_platforms' => OAuthPlatformConstants::getDeveloperPlatforms(),
            'client_types' => OAuthClientTypeConstants::getAllClientTypes(),
        ];
        
        return $this->success($platforms);
    }

    /**
     * @Description("微信小程序专用登录接口")
     * ZGW接口名: motong.oauth.wechatMiniappLogin
     * 微信小程序专用的登录接口，简化了参数要求
     */
    public function wechatMiniappLogin()
    {
        // 验证参数
        $this->validate([
            'code' => 'required|string', // 微信小程序登录码
            'encrypted_data' => 'string', // 加密用户信息（可选）
            'iv' => 'string', // 初始向量（可选）
            'signature' => 'string', // 签名（可选）
            'raw_data' => 'string', // 原始数据（可选）
        ]);

        $code = $this->request->param('code');
        
        // 对于微信小程序，state参数不是必需的，传递空字符串和对应的客户端类型
        $result = $this->oauthService->loginByCode(
            OAuthPlatformConstants::WECHAT_MINIAPP,
            $code,
            '', // 微信小程序登录可以不使用state
            OAuthClientTypeConstants::MINIAPP // 明确指定小程序客户端类型
        );
        
        return $this->success($result);
    }

    /**
     * @Description("微信小程序绑定账号")
     * ZGW接口名: motong.oauth.wechatMiniappBind
     * 微信小程序专用的账号绑定接口
     */
    public function wechatMiniappBind(AuthedRequest $request)
    {
        // 验证参数
        $this->validate([
            'code' => 'required|string', // 微信小程序登录码
            'encrypted_data' => 'string', // 加密用户信息（可选）
            'iv' => 'string', // 初始向量（可选）
        ]);

        $userId = $this->getUserId();
        $code = $request->param('code');

        // 创建临时state用于绑定操作
        $extraData = [
            'encrypted_data' => $request->param('encrypted_data'),
            'iv' => $request->param('iv'),
        ];

        // 先获取授权URL以生成state
        $authResult = $this->oauthService->getAuthUrl(
            OAuthPlatformConstants::WECHAT_MINIAPP,
            OAuthClientTypeConstants::MINIAPP,
            'bind',
            $userId,
            null,
            $extraData
        );

        // 然后进行绑定
        $result = $this->oauthService->bindAccount($userId, OAuthPlatformConstants::WECHAT_MINIAPP, $code, $authResult['state']);
        
        return $this->success($result);
    }

    /**
     * @Description("检查第三方平台账号绑定状态")
     * ZGW接口名: motong.oauth.checkBindStatus
     * 检查用户是否已绑定指定平台的账号
     */
    public function checkBindStatus(AuthedRequest $request)
    {
        // 验证参数
        $this->validate([
            'platform' => 'required|string',
        ]);

        $userId = $this->getUserId();
        $platform = $request->param('platform');

        $bindList = $this->oauthService->getBindList($userId);
        $isBound = false;
        $bindInfo = null;

        foreach ($bindList as $bind) {
            if ($bind['platform'] === $platform) {
                $isBound = true;
                $bindInfo = $bind;
                break;
            }
        }

        return $this->success([
            'is_bound' => $isBound,
            'bind_info' => $bindInfo,
            'platform' => $platform,
            'platform_name' => OAuthPlatformConstants::getPlatformName($platform),
        ]);
    }
}