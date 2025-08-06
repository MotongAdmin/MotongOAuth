<?php

declare(strict_types=1);

namespace Motong\OAuth\Model;

use Hyperf\DbConnection\Model\Model;
use Hyperf\ModelCache\Cacheable;
use Hyperf\ModelCache\CacheableInterface;

/**
 * OAuth第三方平台配置模型
 * 
 * @property int $id 配置ID
 * @property string $name 配置名称
 * @property string $description 配置描述
 * @property string $platform 平台类型
 * @property string $client_type 客户端类型
 * @property string $app_id AppID/ClientID
 * @property string $app_secret AppSecret/ClientSecret(加密存储)
 * @property string $auth_redirect 授权回调地址
 * @property string $scopes 授权范围
 * @property string $message_token 消息校验Token
 * @property string $message_aeskey 消息加解密密钥
 * @property array $extra_config 额外配置
 * @property int $status 状态：1=启用，0=禁用
 * @property int $sort_order 排序权重
 * @property \Carbon\Carbon $created_at 创建时间
 * @property \Carbon\Carbon $updated_at 更新时间
 * 
 * @author Motong OAuth Team
 * @version 1.0.0
 */
class SysOAuthConfig extends Model implements CacheableInterface
{
    use Cacheable;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'sys_oauth_config';

    /**
     * The primary key for the model.
     *
     * @var string
     */
    protected $primaryKey = 'id';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'name',
        'description', 
        'platform',
        'client_type',
        'app_id',
        'app_secret',
        'auth_redirect',
        'scopes',
        'message_token',
        'message_aeskey',
        'extra_config',
        'status',
        'sort_order',
    ];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [
        'id' => 'integer',
        'extra_config' => 'array',
        'status' => 'integer',
        'sort_order' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * The attributes that should be hidden for arrays.
     *
     * @var array
     */
    protected $hidden = [];

    /**
     * 状态常量
     */
    const STATUS_DISABLED = 0; // 禁用
    const STATUS_ENABLED = 1;  // 启用

    /**
     * 默认作用域：仅返回启用的配置
     */
    public function scopeEnabled($query)
    {
        return $query->where('status', self::STATUS_ENABLED);
    }

    /**
     * 根据平台类型查询
     */
    public function scopePlatform($query, string $platform)
    {
        return $query->where('platform', $platform);
    }

    /**
     * 根据客户端类型查询
     */
    public function scopeClientType($query, string $clientType)
    {
        return $query->where('client_type', $clientType);
    }

    /**
     * 根据平台和客户端类型查询
     */
    public function scopePlatformAndClientType($query, string $platform, string $clientType)
    {
        return $query->where('platform', $platform)->where('client_type', $clientType);
    }

    /**
     * 按排序权重排序
     */
    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order', 'desc')->orderBy('created_at', 'desc');
    }

    /**
     * 获取状态显示文本
     * 
     * @return string
     */
    public function getStatusTextAttribute(): string
    {
        return $this->status === self::STATUS_ENABLED ? '启用' : '禁用';
    }

    /**
     * 是否启用
     * 
     * @return bool
     */
    public function isEnabled(): bool
    {
        return $this->status === self::STATUS_ENABLED;
    }

    /**
     * 是否禁用
     * 
     * @return bool
     */
    public function isDisabled(): bool
    {
        return $this->status === self::STATUS_DISABLED;
    }

    /**
     * 启用配置
     * 
     * @return bool
     */
    public function enable(): bool
    {
        return $this->update(['status' => self::STATUS_ENABLED]);
    }

    /**
     * 禁用配置
     * 
     * @return bool
     */
    public function disable(): bool
    {
        return $this->update(['status' => self::STATUS_DISABLED]);
    }

    /**
     * 与用户绑定的关联关系
     */
    public function binds()
    {
        return $this->hasMany(UserOAuthBind::class, 'config_id', 'id');
    }

    /**
     * 与授权状态的关联关系
     */
    public function authStates()
    {
        return $this->hasMany(OAuthAuthState::class, 'config_id', 'id');
    }

    /**
     * 缓存前缀
     * 
     * @return string
     */
    public function getCachePrefix(): string
    {
        return 'oauth:config:';
    }

    /**
     * 缓存时间(秒)
     * 
     * @return int
     */
    public function getCacheTTL(): int
    {
        return 3600; // 1小时
    }
}