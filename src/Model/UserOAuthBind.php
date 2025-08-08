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

namespace Motong\OAuth\Model;

use Hyperf\DbConnection\Model\Model;
use Hyperf\ModelCache\Cacheable;
use Hyperf\ModelCache\CacheableInterface;
use Carbon\Carbon;

/**
 * 用户第三方账号绑定模型
 * 
 * @property int $bind_id 绑定ID
 * @property int $user_id 用户ID
 * @property int $config_id 配置ID
 * @property string $platform 平台类型
 * @property string $openid 第三方平台用户唯一标识
 * @property string $unionid 第三方平台用户UnionID
 * @property string $nickname 第三方平台昵称
 * @property string $avatar 第三方平台头像URL
 * @property int $gender 性别：0=未知，1=男，2=女
 * @property string $country 国家
 * @property string $province 省份
 * @property string $city 城市
 * @property array $oauth_info 第三方平台完整用户信息
 * @property string $access_token 访问令牌(加密存储)
 * @property string $refresh_token 刷新令牌(加密存储)
 * @property int $expires_in token过期时间(秒)
 * @property \Carbon\Carbon $token_expires_at token过期时间点
 * @property \Carbon\Carbon $bind_time 绑定时间
 * @property \Carbon\Carbon $last_login_time 最后登录时间
 * @property int $login_count 登录次数统计
 * @property int $status 绑定状态：1=正常，0=解绑
 * @property \Carbon\Carbon $created_at 创建时间
 * @property \Carbon\Carbon $updated_at 更新时间
 * 
 * @author Motong OAuth Team
 * @version 1.0.0
 */
class UserOAuthBind extends Model implements CacheableInterface
{
    use Cacheable;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'user_oauth_bind';

    /**
     * The primary key for the model.
     *
     * @var string
     */
    protected $primaryKey = 'bind_id';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'user_id',
        'config_id',
        'platform',
        'openid',
        'unionid',
        'nickname',
        'avatar',
        'gender',
        'country',
        'province',
        'city',
        'oauth_info',
        'access_token',
        'refresh_token',
        'expires_in',
        'token_expires_at',
        'bind_time',
        'last_login_time',
        'login_count',
        'status',
    ];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [
        'bind_id' => 'integer',
        'user_id' => 'integer',
        'config_id' => 'integer',
        'gender' => 'integer',
        'oauth_info' => 'array',
        'expires_in' => 'integer',
        'token_expires_at' => 'datetime',
        'bind_time' => 'datetime',
        'last_login_time' => 'datetime',
        'login_count' => 'integer',
        'status' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * The attributes that should be hidden for arrays.
     *
     * @var array
     */
    protected $hidden = [
        'access_token',
        'refresh_token',
    ];

    /**
     * 状态常量
     */
    const STATUS_UNBOUND = 0; // 解绑
    const STATUS_BOUND = 1;   // 正常绑定

    /**
     * 性别常量
     */
    const GENDER_UNKNOWN = 0; // 未知
    const GENDER_MALE = 1;    // 男
    const GENDER_FEMALE = 2;  // 女

    /**
     * 默认作用域：仅返回正常绑定的记录
     */
    public function scopeBound($query)
    {
        return $query->where('status', self::STATUS_BOUND);
    }

    /**
     * 根据用户ID查询
     */
    public function scopeUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    /**
     * 根据平台类型查询
     */
    public function scopePlatform($query, string $platform)
    {
        return $query->where('platform', $platform);
    }

    /**
     * 根据OpenID查询
     */
    public function scopeOpenid($query, string $openid)
    {
        return $query->where('openid', $openid);
    }

    /**
     * 根据UnionID查询
     */
    public function scopeUnionid($query, string $unionid)
    {
        return $query->where('unionid', $unionid);
    }

    /**
     * 根据配置ID查询
     */
    public function scopeConfig($query, int $configId)
    {
        return $query->where('config_id', $configId);
    }

    /**
     * 按最后登录时间排序
     */
    public function scopeOrderByLastLogin($query)
    {
        return $query->orderBy('last_login_time', 'desc');
    }

    /**
     * 获取状态显示文本
     * 
     * @return string
     */
    public function getStatusTextAttribute(): string
    {
        return $this->status === self::STATUS_BOUND ? '正常' : '解绑';
    }

    /**
     * 获取性别显示文本
     * 
     * @return string
     */
    public function getGenderTextAttribute(): string
    {
        switch ($this->gender) {
            case self::GENDER_MALE:
                return '男';
            case self::GENDER_FEMALE:
                return '女';
            default:
                return '未知';
        }
    }

    /**
     * 获取地区显示文本
     * 
     * @return string
     */
    public function getLocationAttribute(): string
    {
        $location = [];
        if ($this->country) {
            $location[] = $this->country;
        }
        if ($this->province && $this->province !== $this->country) {
            $location[] = $this->province;
        }
        if ($this->city && $this->city !== $this->province) {
            $location[] = $this->city;
        }
        
        return implode(' ', $location) ?: '未知';
    }

    /**
     * 是否正常绑定
     * 
     * @return bool
     */
    public function isBound(): bool
    {
        return $this->status === self::STATUS_BOUND;
    }

    /**
     * 是否已解绑
     * 
     * @return bool
     */
    public function isUnbound(): bool
    {
        return $this->status === self::STATUS_UNBOUND;
    }

    /**
     * 是否Token已过期
     * 
     * @return bool
     */
    public function isTokenExpired(): bool
    {
        return $this->token_expires_at && $this->token_expires_at->isPast();
    }

    /**
     * 解除绑定
     * 
     * @return bool
     */
    public function unbind(): bool
    {
        return $this->update(['status' => self::STATUS_UNBOUND]);
    }

    /**
     * 重新绑定
     * 
     * @return bool
     */
    public function rebind(): bool
    {
        return $this->update(['status' => self::STATUS_BOUND]);
    }

    /**
     * 更新登录信息
     * 
     * @return bool
     */
    public function updateLoginInfo(): bool
    {
        return $this->update([
            'last_login_time' => Carbon::now(),
            'login_count' => $this->login_count + 1,
        ]);
    }

    /**
     * 与配置的关联关系
     */
    public function config()
    {
        return $this->belongsTo(SysOAuthConfig::class, 'config_id', 'id');
    }

    /**
     * 缓存前缀
     * 
     * @return string
     */
    public function getCachePrefix(): string
    {
        return 'oauth:bind:';
    }

    /**
     * 缓存时间(秒)
     * 
     * @return int
     */
    public function getCacheTTL(): int
    {
        return 1800; // 30分钟
    }
}