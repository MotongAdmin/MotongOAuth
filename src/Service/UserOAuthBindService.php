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

use Motong\OAuth\Model\UserOAuthBind;
use Hyperf\DbConnection\Db;
use Carbon\Carbon;

/**
 * 用户OAuth绑定服务
 * 
 * @author Motong OAuth Team
 * @version 1.0.0
 */
class UserOAuthBindService
{
    /**
     * 创建绑定关系
     * 
     * @param int $userId 用户ID
     * @param int $configId 配置ID
     * @param string $platform 平台类型
     * @param array $oauthUser 第三方用户信息
     * @return UserOAuthBind
     * @throws \Exception
     */
    public function createBind(int $userId, int $configId, string $platform, array $oauthUser): UserOAuthBind
    {
        // 检查该第三方账号是否已被其他用户绑定
        $existBind = $this->getBindByConfigAndOpenid($configId, $oauthUser['openid']);
        if ($existBind && $existBind->user_id != $userId) {
            throw new \Exception('该第三方账号已被其他用户绑定');
        }

        // 检查用户是否已经绑定了同一个配置
        $userBind = $this->getBindByUserAndConfig($userId, $configId);
        if ($userBind) {
            throw new \Exception('您已绑定该平台账号');
        }

        $bindData = [
            'user_id' => $userId,
            'config_id' => $configId,
            'platform' => $platform,
            'openid' => $oauthUser['openid'],
            'unionid' => $oauthUser['unionid'] ?? null,
            'nickname' => $oauthUser['nickname'] ?? '',
            'avatar' => $oauthUser['avatar'] ?? '',
            'gender' => $oauthUser['gender'] ?? 0,
            'country' => $oauthUser['country'] ?? '',
            'province' => $oauthUser['province'] ?? '',
            'city' => $oauthUser['city'] ?? '',
            'oauth_info' => $oauthUser,
            'bind_time' => Carbon::now(),
            'last_login_time' => Carbon::now(),
            'login_count' => 1,
        ];

        // 如果有访问令牌，加密存储
        if (!empty($oauthUser['access_token'])) {
            $bindData['access_token'] = $this->encryptToken($oauthUser['access_token']);
        }

        if (!empty($oauthUser['refresh_token'])) {
            $bindData['refresh_token'] = $this->encryptToken($oauthUser['refresh_token']);
        }

        if (!empty($oauthUser['expires_in'])) {
            $bindData['expires_in'] = $oauthUser['expires_in'];
            $bindData['token_expires_at'] = Carbon::now()->addSeconds($oauthUser['expires_in']);
        }

        return UserOAuthBind::create($bindData);
    }

    /**
     * 根据配置ID和OpenID获取绑定信息
     * 
     * @param int $configId 配置ID
     * @param string $openid OpenID
     * @return UserOAuthBind|null
     */
    public function getBindByConfigAndOpenid(int $configId, string $openid): ?UserOAuthBind
    {
        return UserOAuthBind::openid($openid)
            ->config($configId)
            ->bound()
            ->first();
    }

    /**
     * 根据平台和OpenID获取绑定信息
     * 
     * @param string $platform 平台类型
     * @param string $openid OpenID
     * @return UserOAuthBind|null
     */
    public function getBindByPlatformAndOpenid(string $platform, string $openid): ?UserOAuthBind
    {
        return UserOAuthBind::platform($platform)
            ->openid($openid)
            ->bound()
            ->first();
    }

    /**
     * 根据用户ID和配置ID获取绑定信息
     * 
     * @param int $userId 用户ID
     * @param int $configId 配置ID
     * @return UserOAuthBind|null
     */
    public function getBindByUserAndConfig(int $userId, int $configId): ?UserOAuthBind
    {
        return UserOAuthBind::user($userId)
            ->config($configId)
            ->bound()
            ->first();
    }

    /**
     * 根据用户ID和平台获取绑定信息
     * 
     * @param int $userId 用户ID
     * @param string $platform 平台类型
     * @return UserOAuthBind|null
     */
    public function getBindByUserAndPlatform(int $userId, string $platform): ?UserOAuthBind
    {
        return UserOAuthBind::user($userId)
            ->platform($platform)
            ->bound()
            ->first();
    }

    /**
     * 获取用户的所有绑定
     * 
     * @param int $userId 用户ID
     * @return \Hyperf\Database\Model\Collection
     */
    public function getBindsByUser(int $userId)
    {
        return UserOAuthBind::user($userId)
            ->bound()
            ->with('config')
            ->orderBy('last_login_time', 'desc')
            ->get();
    }

    /**
     * 获取用户绑定列表（用于前端展示）
     * 
     * @param int $userId 用户ID
     * @return array
     */
    public function getUserBindList(int $userId): array
    {
        $binds = $this->getBindsByUser($userId);
        
        return $binds->map(function ($bind) {
            return [
                'bind_id' => $bind->bind_id,
                'platform' => $bind->platform,
                'platform_name' => $bind->config ? $bind->config->name : '未知平台',
                'nickname' => $bind->nickname,
                'avatar' => $bind->avatar,
                'bind_time' => $bind->bind_time ? $bind->bind_time->toDateTimeString() : null,
                'last_login_time' => $bind->last_login_time ? $bind->last_login_time->toDateTimeString() : null,
                'login_count' => $bind->login_count,
            ];
        })->toArray();
    }

    /**
     * 更新绑定信息
     * 
     * @param int $bindId 绑定ID
     * @param array $data 更新数据
     * @return bool
     */
    public function updateBindInfo(int $bindId, array $data): bool
    {
        $bind = UserOAuthBind::findOrFail($bindId);
        
        // 加密敏感信息
        if (isset($data['access_token'])) {
            $data['access_token'] = $this->encryptToken($data['access_token']);
        }
        
        if (isset($data['refresh_token'])) {
            $data['refresh_token'] = $this->encryptToken($data['refresh_token']);
        }

        // 处理过期时间
        if (isset($data['expires_in'])) {
            $data['token_expires_at'] = Carbon::now()->addSeconds($data['expires_in']);
        }
        
        return $bind->update($data);
    }

    /**
     * 更新登录信息
     * 
     * @param int $bindId 绑定ID
     * @return bool
     */
    public function updateLoginInfo(int $bindId): bool
    {
        $bind = UserOAuthBind::findOrFail($bindId);
        return $bind->updateLoginInfo();
    }

    /**
     * 删除绑定（软删除）
     * 
     * @param int $bindId 绑定ID
     * @return bool
     */
    public function deleteBind(int $bindId): bool
    {
        $bind = UserOAuthBind::findOrFail($bindId);
        return $bind->unbind();
    }

    /**
     * 根据用户ID和平台删除绑定
     * 
     * @param int $userId 用户ID
     * @param string $platform 平台类型
     * @return bool
     */
    public function deleteBindByUserAndPlatform(int $userId, string $platform): bool
    {
        $bind = $this->getBindByUserAndPlatform($userId, $platform);
        
        if (!$bind) {
            throw new \Exception('该平台未绑定');
        }
        
        return $bind->unbind();
    }

    /**
     * 恢复绑定
     * 
     * @param int $bindId 绑定ID
     * @return bool
     */
    public function restoreBind(int $bindId): bool
    {
        $bind = UserOAuthBind::findOrFail($bindId);
        return $bind->rebind();
    }

    /**
     * 批量删除用户的所有绑定
     * 
     * @param int $userId 用户ID
     * @return int 删除的数量
     */
    public function deleteAllBindsByUser(int $userId): int
    {
        return UserOAuthBind::where('user_id', $userId)
            ->bound()
            ->update(['status' => UserOAuthBind::STATUS_UNBOUND]);
    }

    /**
     * 获取绑定统计信息
     * 
     * @param array $filters 过滤条件
     * @return array
     */
    public function getBindStats(array $filters = []): array
    {
        $query = UserOAuthBind::bound();
        
        // 应用过滤条件
        if (!empty($filters['platform'])) {
            $query->platform($filters['platform']);
        }
        
        if (!empty($filters['start_date']) && !empty($filters['end_date'])) {
            $query->whereBetween('bind_time', [$filters['start_date'], $filters['end_date']]);
        }
        
        $total = $query->count();
        
        // 按平台分组统计
        $platformStats = UserOAuthBind::bound()
            ->select('platform', Db::raw('count(*) as count'))
            ->groupBy('platform')
            ->get()
            ->pluck('count', 'platform')
            ->toArray();
        
        // 近7天每日绑定数量
        $dailyStats = UserOAuthBind::bound()
            ->select(Db::raw('DATE(bind_time) as date'), Db::raw('count(*) as count'))
            ->where('bind_time', '>=', Carbon::now()->subDays(7))
            ->groupBy('date')
            ->orderBy('date')
            ->get()
            ->toArray();
        
        return [
            'total' => $total,
            'platform_stats' => $platformStats,
            'daily_stats' => $dailyStats,
        ];
    }

    /**
     * 清理无效的绑定记录
     * 
     * @return int 清理的数量
     */
    public function cleanInvalidBinds(): int
    {
        // 删除配置已被删除的绑定记录
        $deletedConfigIds = UserOAuthBind::leftJoin('sys_oauth_config', 'user_oauth_bind.config_id', '=', 'sys_oauth_config.id')
            ->whereNull('sys_oauth_config.id')
            ->pluck('user_oauth_bind.bind_id')
            ->toArray();
        
        if (!empty($deletedConfigIds)) {
            return UserOAuthBind::whereIn('bind_id', $deletedConfigIds)->delete();
        }
        
        return 0;
    }

    /**
     * 根据UnionID查找用户绑定
     * 
     * @param string $unionid UnionID
     * @return \Hyperf\Database\Model\Collection
     */
    public function getBindsByUnionid(string $unionid)
    {
        return UserOAuthBind::unionid($unionid)
            ->bound()
            ->get();
    }

    /**
     * 检查用户是否绑定了指定平台
     * 
     * @param int $userId 用户ID
     * @param string $platform 平台类型
     * @return bool
     */
    public function hasUserBoundPlatform(int $userId, string $platform): bool
    {
        return UserOAuthBind::user($userId)
            ->platform($platform)
            ->bound()
            ->exists();
    }

    /**
     * 获取用户绑定的平台列表
     * 
     * @param int $userId 用户ID
     * @return array
     */
    public function getUserBoundPlatforms(int $userId): array
    {
        return UserOAuthBind::user($userId)
            ->bound()
            ->pluck('platform')
            ->unique()
            ->toArray();
    }

    /**
     * Token加密
     * 
     * @param string $token 原始token
     * @return string
     */
    private function encryptToken(string $token): string
    {
        return base64_encode($token); 
    }

    /**
     * Token解密
     * 
     * @param string $encryptedToken 加密的token
     * @return string
     */
    private function decryptToken(string $encryptedToken): string
    {
        return base64_decode($encryptedToken);
    }
}