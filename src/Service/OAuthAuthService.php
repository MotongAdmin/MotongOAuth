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
use Motong\OAuth\Model\UserOAuthBind;
use Motong\OAuth\Model\OAuthAuthState;
use Motong\OAuth\Model\OAuthLoginLog;
use Motong\OAuth\Service\Provider\AbstractOAuthProvider;
use Motong\OAuth\Service\Provider\WeChatMiniappProvider;
use Motong\OAuth\Constants\OAuthPlatformConstants;
use Motong\OAuth\Constants\OAuthClientTypeConstants;
use Carbon\Carbon;
use ZYProSoft\Service\AbstractService;
use App\Service\Common\UserService;
use App\Model\User;
use Hyperf\HttpServer\Contract\RequestInterface;
use Hyperf\Contract\ContainerInterface;

/**
 * OAuth认证服务
 * 
 * @author Motong OAuth Team
 * @version 1.0.0  
 */
class OAuthAuthService extends AbstractService
{
    /**
     * OAuth配置服务（基础版本，用于认证）
     */
    private OAuthConfigService $configService;

    /**
     * 用户绑定服务
     */
    private UserOAuthBindService $bindService;

    /**
     * 用户服务
     */
    private UserService $userService;

    /**
     * Provider工厂映射
     */
    private array $providers = [
        OAuthPlatformConstants::WECHAT_MINIAPP => WeChatMiniappProvider::class,
        // 可以继续添加其他平台的Provider
    ];

    public function __construct(ContainerInterface $container)
    {
        parent::__construct($container);
        $this->configService = new OAuthConfigService();
        $this->bindService = new UserOAuthBindService();
        $this->userService = new UserService($this->container);
    }

    /**
     * 获取OAuth授权URL
     * 
     * @param string $platform 平台类型
     * @param string $clientType 客户端类型
     * @param string $action 操作类型：login=登录，bind=绑定
     * @param int|null $userId 用户ID（绑定操作时必需）
     * @param string|null $redirectUrl 登录成功后的跳转地址
     * @param array $extraData 额外数据
     * @return array
     * @throws \Exception
     */
    public function getAuthUrl(string $platform, string $clientType, string $action = 'login', ?int $userId = null, ?string $redirectUrl = null, array $extraData = []): array
    {
        // 验证操作类型
        if (!in_array($action, [OAuthAuthState::ACTION_LOGIN, OAuthAuthState::ACTION_BIND])) {
            throw new \Exception('无效的操作类型');
        }

        // 绑定操作必须提供用户ID
        if ($action === OAuthAuthState::ACTION_BIND && !$userId) {
            throw new \Exception('绑定操作必须提供用户ID');
        }

        // 获取配置
        $config = $this->configService->getConfigByPlatform($platform, $clientType);
        if (!$config) {
            throw new \Exception('OAuth配置不存在或已禁用');
        }

        // 创建Provider
        $provider = $this->createProvider($platform, $config);
        
        // 创建授权状态记录
        $stateRecord = OAuthAuthState::createState([
            'config_id' => $config['id'],
            'platform' => $platform,
            'client_type' => $clientType,
            'action' => $action,
            'user_id' => $userId,
            'redirect_url' => $redirectUrl,
            'extra_data' => $extraData,
            'ip_address' => $this->getClientIp(),
            'user_agent' => $this->getUserAgent()
        ]);
        
        // 获取授权URL
        $authUrl = $provider->getAuthUrl($stateRecord->state);
        
        return [
            'auth_url' => $authUrl,
            'state' => $stateRecord->state,
            'expires_at' => $stateRecord->expires_at->toDateTimeString(),
        ];
    }

    /**
     * 通过授权码登录
     * 
     * @param string $platform 平台类型
     * @param string $code 授权码
     * @param string $state 状态参数（微信小程序可为空）
     * @param string $clientType 客户端类型（当state为空时使用）
     * @return array
     * @throws \Exception
     */
    public function loginByCode(string $platform, string $code, string $state = '', string $clientType = ''): array
    {
        $logData = [
            'platform' => $platform,
            'action' => OAuthLoginLog::ACTION_LOGIN,
            'client_type' => '',
            'openid' => '',
            'ip_address' => $this->getClientIp(),
            'user_agent' => $this->getUserAgent(),
            'request_data' => compact('platform', 'code', 'state'),
        ];

        try {
            $stateRecord = null;
            $actualClientType = '';
            
            // 如果有state参数，则验证state
            if (!empty($state)) {
                $stateRecord = $this->validateState($state);
                if (!$stateRecord || $stateRecord->platform !== $platform) {
                    throw new \Exception('state参数无效或已过期');
                }

                if ($stateRecord->action !== OAuthAuthState::ACTION_LOGIN) {
                    throw new \Exception('state参数操作类型不匹配');
                }

                $actualClientType = $stateRecord->client_type;
            } else {
                // 无state参数的情况（如微信小程序登录）
                if (empty($clientType)) {
                    // 为不同平台设置默认的客户端类型
                    $actualClientType = $this->getDefaultClientType($platform);
                } else {
                    $actualClientType = $clientType;
                }
            }

            $logData['client_type'] = $actualClientType;

            // 获取配置
            $config = $this->configService->getConfigByPlatform($platform, $actualClientType);
            if (!$config) {
                throw new \Exception("OAuth配置不存在或已禁用: platform={$platform}, client_type={$actualClientType}");
            }

            // 创建Provider并获取用户信息
            $provider = $this->createProvider($platform, $config);
            $oauthUser = $provider->getUserByCode($code);
            
            $logData['openid'] = $oauthUser['openid'] ?? '';

            // 查找是否已绑定用户
            $bind = $this->bindService->getBindByConfigAndOpenid($config['id'], $oauthUser['openid']);
            
            if ($bind) {
                // 已绑定用户，直接登录
                $user = $this->getUserById($bind->user_id);
                if (!$user || !$this->isUserActive($user)) {
                    throw new \Exception('用户不存在或已禁用');
                }
                
                // 更新登录信息
                $this->bindService->updateLoginInfo($bind->bind_id);
                
                $isNewUser = false;
                $logData['user_id'] = $user['user_id'];
            } else {
                // 新用户，创建账号并绑定
                $user = $this->createUserFromOAuth($oauthUser, $platform);
                $bind = $this->bindService->createBind($user->user_id, $config['id'], $platform, $oauthUser);
                $isNewUser = true;
                $logData['user_id'] = $user->user_id;
            }
            
            // 标记state为已使用（如果有state记录的话）
            if ($stateRecord) {
                $stateRecord->markAsUsed();
            }
            
            // 生成用户令牌
            $token = $this->generateUserToken($user);
            
            $result = [
                'token' => $token,
                'user' => [
                    'user_id' => $user['user_id'],
                    'username' => $user['username'] ?? '',
                    'nickname' => $user['nickname'] ?? $oauthUser['nickname'] ?? '',
                    'avatar' => $user['avatar'] ?? $oauthUser['avatar'] ?? '',
                ],
                'is_new_user' => $isNewUser,
                'platform' => $platform,
                'bind_info' => [
                    'bind_id' => $bind->bind_id,
                    'platform' => $bind->platform,
                    'nickname' => $bind->nickname,
                    'avatar' => $bind->avatar,
                ]
            ];

            // 记录成功日志
            $logData['response_data'] = [
                'user_id' => $user['user_id'],
                'is_new_user' => $isNewUser
            ];
            OAuthLoginLog::logSuccess($logData);

            return $result;

        } catch (\Exception $e) {
            // 记录失败日志
            $logData['error_message'] = $e->getMessage();
            OAuthLoginLog::logFail($logData);
            
            throw $e;
        }
    }

    /**
     * 绑定第三方账号
     * 
     * @param int $userId 用户ID
     * @param string $platform 平台类型
     * @param string $code 授权码
     * @param string $state 状态参数
     * @return array
     * @throws \Exception
     */
    public function bindAccount(int $userId, string $platform, string $code, string $state): array
    {
        $logData = [
            'user_id' => $userId,
            'platform' => $platform,
            'action' => OAuthLoginLog::ACTION_BIND,
            'client_type' => '',
            'openid' => '',
            'ip_address' => $this->getClientIp(),
            'user_agent' => $this->getUserAgent(),
            'request_data' => compact('userId', 'platform', 'code', 'state'),
        ];

        try {
            // 验证state
            $stateRecord = $this->validateState($state);
            if (!$stateRecord || $stateRecord->platform !== $platform) {
                throw new \Exception('state参数无效或已过期');
            }

            if ($stateRecord->action !== OAuthAuthState::ACTION_BIND) {
                throw new \Exception('state参数操作类型不匹配');
            }

            if ($stateRecord->user_id != $userId) {
                throw new \Exception('用户身份验证失败');
            }

            $logData['client_type'] = $stateRecord->client_type;

            // 获取配置
            $config = $this->configService->getConfigByPlatform($platform, $stateRecord->client_type);
            if (!$config) {
                throw new \Exception('OAuth配置不存在或已禁用');
            }

            // 创建Provider并获取用户信息
            $provider = $this->createProvider($platform, $config);
            $oauthUser = $provider->getUserByCode($code);
            
            $logData['openid'] = $oauthUser['openid'] ?? '';

            // 检查该第三方账号是否已被其他用户绑定
            $existBind = $this->bindService->getBindByConfigAndOpenid($config['id'], $oauthUser['openid']);
            if ($existBind && $existBind->user_id != $userId) {
                throw new \Exception('该第三方账号已被其他用户绑定');
            }

            // 检查用户是否已经绑定了同一个配置
            if ($existBind && $existBind->user_id == $userId) {
                throw new \Exception('您已绑定该平台账号');
            }

            // 创建绑定
            $bind = $this->bindService->createBind($userId, $config['id'], $platform, $oauthUser);
            
            // 标记state为已使用
            $stateRecord->markAsUsed();
            
            $result = [
                'bind_id' => $bind->bind_id,
                'platform' => $platform,
                'platform_name' => $config['name'],
                'nickname' => $oauthUser['nickname'] ?? '',
                'avatar' => $oauthUser['avatar'] ?? '',
                'bind_time' => $bind->bind_time->toDateTimeString(),
            ];

            // 记录成功日志
            $logData['response_data'] = [
                'bind_id' => $bind->bind_id
            ];
            OAuthLoginLog::logSuccess($logData);

            return $result;

        } catch (\Exception $e) {
            // 记录失败日志
            $logData['error_message'] = $e->getMessage();
            OAuthLoginLog::logFail($logData);
            
            throw $e;
        }
    }

    /**
     * 解除绑定
     * 
     * @param int $userId 用户ID
     * @param string $platform 平台类型
     * @return bool
     * @throws \Exception
     */
    public function unbindAccount(int $userId, string $platform): bool
    {
        $logData = [
            'user_id' => $userId,
            'platform' => $platform,
            'action' => OAuthLoginLog::ACTION_UNBIND,
            'client_type' => '',
            'openid' => '',
            'ip_address' => $this->getClientIp(),
            'user_agent' => $this->getUserAgent(),
            'request_data' => compact('userId', 'platform'),
        ];

        try {
            // 查找绑定记录
            $bind = $this->bindService->getBindByUserAndPlatform($userId, $platform);
            if (!$bind) {
                throw new \Exception('该平台未绑定');
            }

            $logData['openid'] = $bind->openid;

            // 解除绑定
            $result = $this->bindService->deleteBind($bind->bind_id);
            
            // 记录成功日志
            $logData['response_data'] = [
                'bind_id' => $bind->bind_id
            ];
            OAuthLoginLog::logSuccess($logData);

            return $result;

        } catch (\Exception $e) {
            // 记录失败日志
            $logData['error_message'] = $e->getMessage();
            OAuthLoginLog::logFail($logData);
            
            throw $e;
        }
    }

    /**
     * 获取用户绑定列表
     * 
     * @param int $userId 用户ID
     * @return array
     */
    public function getBindList(int $userId): array
    {
        return $this->bindService->getUserBindList($userId);
    }

    /**
     * 刷新访问令牌
     * 
     * @param int $bindId 绑定ID
     * @return array
     * @throws \Exception
     */
    public function refreshAccessToken(int $bindId): array
    {
        $bind = UserOAuthBind::findOrFail($bindId)->with('config')->first();
        
        if (!$bind->refresh_token) {
            throw new \Exception('没有刷新令牌，无法刷新');
        }

        $config = $this->configService->getConfigByPlatform($bind->platform, $bind->config->client_type);
        if (!$config) {
            throw new \Exception('OAuth配置不存在或已禁用');
        }

        $provider = $this->createProvider($bind->platform, $config);
        
        try {
            $tokenData = $provider->refreshToken($bind->refresh_token);
            
            // 更新令牌信息
            $this->bindService->updateBindInfo($bindId, [
                'access_token' => $tokenData['access_token'],
                'refresh_token' => $tokenData['refresh_token'] ?? $bind->refresh_token,
                'expires_in' => $tokenData['expires_in'] ?? null,
            ]);

            return [
                'access_token' => $tokenData['access_token'],
                'expires_in' => $tokenData['expires_in'] ?? null,
                'refresh_time' => Carbon::now()->toDateTimeString(),
            ];

        } catch (\Exception $e) {
            throw new \Exception("刷新令牌失败: " . $e->getMessage());
        }
    }

    /**
     * 创建OAuth提供者
     * 
     * @param string $platform 平台类型
     * @param array $config 配置信息
     * @return AbstractOAuthProvider
     * @throws \Exception
     */
    private function createProvider(string $platform, array $config): AbstractOAuthProvider
    {
        if (!isset($this->providers[$platform])) {
            throw new \Exception("不支持的OAuth平台: {$platform}");
        }

        $providerClass = $this->providers[$platform];
        
        if (!class_exists($providerClass)) {
            throw new \Exception("Provider类不存在: {$providerClass}");
        }

        return new $providerClass($config);
    }

    /**
     * 验证state参数
     * 
     * @param string $state state参数
     * @return OAuthAuthState|null
     */
    private function validateState(string $state): ?OAuthAuthState
    {
        return OAuthAuthState::validateState($state);
    }

    /**
     * 从OAuth信息创建用户
     * 
     * @param array $oauthUser OAuth用户信息
     * @param string $platform 平台类型
     * @return User
     */
    private function createUserFromOAuth(array $oauthUser, string $platform): User
    {
        // 生成唯一的用户名
        $rand = mt_rand(1000, 9999);
        $username = $platform . '_' . Carbon::now()->getTimestampMs();
        
        // 数据结构
        $userData = [
            'username' => $username,
            'nickname' => $oauthUser['nickname'] ?? '新用户' . $rand,
            'avatar' => $oauthUser['avatar'] ?? '',
        ];

        // 调用用户服务创建真实用户
        $user = $this->userService->createUserForOAuth($userData);
        
        return $user;
    }

    /**
     * 根据用户ID获取用户信息
     * 
     * @param int $userId 用户ID
     * @return User|null
     */
    private function getUserById(int $userId): ?User
    {
        return $this->userService->getUserById($userId);
    }

    /**
     * 检查用户是否活跃
     * 
     * @param User $user 用户信息
     * @return bool
     */
    private function isUserActive(User $user): bool
    {
        return $user->status == 1;
    }

    /**
     * 生成用户令牌
     * 
     * @param User $user 用户信息
     * @return string
     */
    private function generateUserToken(User $user): string
    {
        return $this->auth->login($user);
    }

    /**
     * 获取客户端IP
     * 
     * @return string
     */
    private function getClientIp(): string
    {
        // 获取请求头信息
        $request = $this->container->get(RequestInterface::class);
        return $request->getHeaderLine('x-real-ip') ?: $request->getServerParams()['remote_addr'];
    }

    /**
     * 获取用户代理
     * 
     * @return string
     */
    private function getUserAgent(): string
    {
        $request = $this->container->get(RequestInterface::class);
        return $request->getHeaderLine('user-agent') ?? '';
    }

    /**
     * 获取平台默认的客户端类型
     * 
     * @param string $platform 平台类型
     * @return string
     */
    private function getDefaultClientType(string $platform): string
    {
        // 根据平台返回默认的客户端类型
        switch ($platform) {
            case OAuthPlatformConstants::WECHAT_MINIAPP:
                return OAuthClientTypeConstants::MINIAPP;
            case OAuthPlatformConstants::ALIPAY_MINIAPP:
                return OAuthClientTypeConstants::MINIAPP;
            case OAuthPlatformConstants::WECHAT_MP:
                return OAuthClientTypeConstants::WEB;
            case OAuthPlatformConstants::WECHAT_OPEN:
                return OAuthClientTypeConstants::APP;
            case OAuthPlatformConstants::ALIPAY_APP:
                return OAuthClientTypeConstants::APP;
            case OAuthPlatformConstants::QQ_APP:
                return OAuthClientTypeConstants::APP;
            default:
                return OAuthClientTypeConstants::WEB; // 默认使用WEB类型
        }
    }
}