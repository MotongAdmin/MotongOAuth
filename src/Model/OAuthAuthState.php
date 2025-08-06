<?php

declare(strict_types=1);

namespace Motong\OAuth\Model;

use Hyperf\DbConnection\Model\Model;
use Carbon\Carbon;

/**
 * OAuth授权状态记录模型
 * 
 * @property int $id ID
 * @property string $state OAuth state参数
 * @property int $config_id 配置ID
 * @property string $platform 平台类型
 * @property string $client_type 客户端类型
 * @property string $action 操作类型：login=登录，bind=绑定
 * @property int $user_id 用户ID(绑定操作时必需)
 * @property string $redirect_url 登录成功后的跳转地址
 * @property array $extra_data 额外数据
 * @property string $ip_address 请求IP地址
 * @property string $user_agent 用户代理
 * @property \Carbon\Carbon $expires_at 过期时间
 * @property \Carbon\Carbon $used_at 使用时间
 * @property \Carbon\Carbon $created_at 创建时间
 * 
 * @author Motong OAuth Team
 * @version 1.0.0
 */
class OAuthAuthState extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'oauth_auth_state';

    /**
     * The primary key for the model.
     *
     * @var string
     */
    protected $primaryKey = 'id';

    /**
     * Indicates if the model should be timestamped.
     *
     * @var bool
     */
    public $timestamps = false;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'state',
        'config_id',
        'platform',
        'client_type',
        'action',
        'user_id',
        'redirect_url',
        'extra_data',
        'ip_address',
        'user_agent',
        'expires_at',
        'used_at',
        'created_at',
    ];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [
        'id' => 'integer',
        'config_id' => 'integer',
        'user_id' => 'integer',
        'extra_data' => 'array',
        'expires_at' => 'datetime',
        'used_at' => 'datetime',
        'created_at' => 'datetime',
    ];

    /**
     * 操作类型常量
     */
    const ACTION_LOGIN = 'login'; // 登录
    const ACTION_BIND = 'bind';   // 绑定

    /**
     * 根据state查询
     */
    public function scopeState($query, string $state)
    {
        return $query->where('state', $state);
    }

    /**
     * 根据平台类型查询
     */
    public function scopePlatform($query, string $platform)
    {
        return $query->where('platform', $platform);
    }

    /**
     * 根据操作类型查询
     */
    public function scopeAction($query, string $action)
    {
        return $query->where('action', $action);
    }

    /**
     * 根据用户ID查询
     */
    public function scopeUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    /**
     * 查询未过期的记录
     */
    public function scopeNotExpired($query)
    {
        return $query->where('expires_at', '>', Carbon::now());
    }

    /**
     * 查询已过期的记录
     */
    public function scopeExpired($query)
    {
        return $query->where('expires_at', '<=', Carbon::now());
    }

    /**
     * 查询未使用的记录
     */
    public function scopeNotUsed($query)
    {
        return $query->whereNull('used_at');
    }

    /**
     * 查询已使用的记录
     */
    public function scopeUsed($query)
    {
        return $query->whereNotNull('used_at');
    }

    /**
     * 获取操作类型显示文本
     * 
     * @return string
     */
    public function getActionTextAttribute(): string
    {
        return $this->action === self::ACTION_LOGIN ? '登录' : '绑定';
    }

    /**
     * 是否登录操作
     * 
     * @return bool
     */
    public function isLoginAction(): bool
    {
        return $this->action === self::ACTION_LOGIN;
    }

    /**
     * 是否绑定操作
     * 
     * @return bool
     */
    public function isBindAction(): bool
    {
        return $this->action === self::ACTION_BIND;
    }

    /**
     * 是否已过期
     * 
     * @return bool
     */
    public function isExpired(): bool
    {
        return $this->expires_at && $this->expires_at->isPast();
    }

    /**
     * 是否已使用
     * 
     * @return bool
     */
    public function isUsed(): bool
    {
        return !is_null($this->used_at);
    }

    /**
     * 是否有效(未过期且未使用)
     * 
     * @return bool
     */
    public function isValid(): bool
    {
        return !$this->isExpired() && !$this->isUsed();
    }

    /**
     * 标记为已使用
     * 
     * @return bool
     */
    public function markAsUsed(): bool
    {
        return $this->update(['used_at' => Carbon::now()]);
    }

    /**
     * 与配置的关联关系
     */
    public function config()
    {
        return $this->belongsTo(SysOAuthConfig::class, 'config_id', 'id');
    }

    /**
     * 生成随机state
     * 
     * @return string
     */
    public static function generateState(): string
    {
        return bin2hex(random_bytes(16)); // 32位随机字符串
    }

    /**
     * 创建OAuth授权状态记录
     * 
     * @param array $data
     * @return static
     */
    public static function createState(array $data): self
    {
        $data['state'] = self::generateState();
        $data['created_at'] = Carbon::now();
        
        // 设置默认过期时间为15分钟
        if (!isset($data['expires_at'])) {
            $data['expires_at'] = Carbon::now()->addMinutes(15);
        }
        
        return self::create($data);
    }

    /**
     * 验证并获取state数据
     * 
     * @param string $state
     * @return static|null
     */
    public static function validateState(string $state): ?self
    {
        return self::where('state', $state)
            ->notExpired()
            ->notUsed()
            ->first();
    }

    /**
     * 清理过期的state记录
     * 
     * @return int 清理的记录数
     */
    public static function cleanExpiredStates(): int
    {
        return self::expired()->delete();
    }
}