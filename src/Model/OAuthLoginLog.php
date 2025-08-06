<?php

declare(strict_types=1);

namespace Motong\OAuth\Model;

use Hyperf\DbConnection\Model\Model;
use Carbon\Carbon;

/**
 * OAuth登录日志模型
 * 
 * @property int $log_id 日志ID
 * @property int $user_id 用户ID
 * @property string $platform 平台类型
 * @property string $openid 第三方openid
 * @property string $action 操作类型：login=登录，bind=绑定，unbind=解绑
 * @property string $client_type 客户端类型
 * @property string $result 结果：success=成功，fail=失败
 * @property string $error_message 错误信息
 * @property string $ip_address IP地址
 * @property string $user_agent 用户代理
 * @property array $request_data 请求数据
 * @property array $response_data 响应数据
 * @property \Carbon\Carbon $created_at 创建时间
 * 
 * @author Motong OAuth Team
 * @version 1.0.0
 */
class OAuthLoginLog extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'oauth_login_log';

    /**
     * The primary key for the model.
     *
     * @var string
     */
    protected $primaryKey = 'log_id';

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
        'user_id',
        'platform',
        'openid',
        'action',
        'client_type',
        'result',
        'error_message',
        'ip_address',
        'user_agent',
        'request_data',
        'response_data',
        'created_at',
    ];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [
        'log_id' => 'integer',
        'user_id' => 'integer',
        'request_data' => 'array',
        'response_data' => 'array',
        'created_at' => 'datetime',
    ];

    /**
     * 操作类型常量
     */
    const ACTION_LOGIN = 'login';   // 登录
    const ACTION_BIND = 'bind';     // 绑定
    const ACTION_UNBIND = 'unbind'; // 解绑

    /**
     * 结果常量
     */
    const RESULT_SUCCESS = 'success'; // 成功
    const RESULT_FAIL = 'fail';       // 失败

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
     * 根据操作类型查询
     */
    public function scopeAction($query, string $action)
    {
        return $query->where('action', $action);
    }

    /**
     * 根据结果查询
     */
    public function scopeResult($query, string $result)
    {
        return $query->where('result', $result);
    }

    /**
     * 查询成功的记录
     */
    public function scopeSuccess($query)
    {
        return $query->where('result', self::RESULT_SUCCESS);
    }

    /**
     * 查询失败的记录
     */
    public function scopeFail($query)
    {
        return $query->where('result', self::RESULT_FAIL);
    }

    /**
     * 根据时间范围查询
     */
    public function scopeDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('created_at', [$startDate, $endDate]);
    }

    /**
     * 按创建时间倒序
     */
    public function scopeLatest($query)
    {
        return $query->orderBy('created_at', 'desc');
    }

    /**
     * 获取操作类型显示文本
     * 
     * @return string
     */
    public function getActionTextAttribute(): string
    {
        switch ($this->action) {
            case self::ACTION_LOGIN:
                return '登录';
            case self::ACTION_BIND:
                return '绑定';
            case self::ACTION_UNBIND:
                return '解绑';
            default:
                return '未知';
        }
    }

    /**
     * 获取结果显示文本
     * 
     * @return string
     */
    public function getResultTextAttribute(): string
    {
        return $this->result === self::RESULT_SUCCESS ? '成功' : '失败';
    }

    /**
     * 是否成功
     * 
     * @return bool
     */
    public function isSuccess(): bool
    {
        return $this->result === self::RESULT_SUCCESS;
    }

    /**
     * 是否失败
     * 
     * @return bool
     */
    public function isFail(): bool
    {
        return $this->result === self::RESULT_FAIL;
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
     * 是否解绑操作
     * 
     * @return bool
     */
    public function isUnbindAction(): bool
    {
        return $this->action === self::ACTION_UNBIND;
    }

    /**
     * 记录登录日志
     * 
     * @param array $data
     * @return static
     */
    public static function log(array $data): self
    {
        $data['created_at'] = Carbon::now();
        return self::create($data);
    }

    /**
     * 记录成功日志
     * 
     * @param array $data
     * @return static
     */
    public static function logSuccess(array $data): self
    {
        $data['result'] = self::RESULT_SUCCESS;
        return self::log($data);
    }

    /**
     * 记录失败日志
     * 
     * @param array $data
     * @return static
     */
    public static function logFail(array $data): self
    {
        $data['result'] = self::RESULT_FAIL;
        return self::log($data);
    }

    /**
     * 获取用户在指定平台的登录统计
     * 
     * @param int $userId
     * @param string $platform
     * @return array
     */
    public static function getUserPlatformStats(int $userId, string $platform): array
    {
        $total = self::where('user_id', $userId)
            ->where('platform', $platform)
            ->where('action', self::ACTION_LOGIN)
            ->count();

        $success = self::where('user_id', $userId)
            ->where('platform', $platform)
            ->where('action', self::ACTION_LOGIN)
            ->where('result', self::RESULT_SUCCESS)
            ->count();

        $lastLogin = self::where('user_id', $userId)
            ->where('platform', $platform)
            ->where('action', self::ACTION_LOGIN)
            ->where('result', self::RESULT_SUCCESS)
            ->latest()
            ->value('created_at');

        return [
            'total_attempts' => $total,
            'success_count' => $success,
            'fail_count' => $total - $success,
            'success_rate' => $total > 0 ? round($success / $total * 100, 2) : 0,
            'last_login_time' => $lastLogin,
        ];
    }

    /**
     * 获取平台登录统计
     * 
     * @param string $platform
     * @param string $startDate
     * @param string $endDate
     * @return array
     */
    public static function getPlatformStats(string $platform, string $startDate = null, string $endDate = null): array
    {
        $query = self::where('platform', $platform)->where('action', self::ACTION_LOGIN);
        
        if ($startDate && $endDate) {
            $query->dateRange($startDate, $endDate);
        }

        $total = $query->count();
        $success = $query->where('result', self::RESULT_SUCCESS)->count();

        return [
            'total_attempts' => $total,
            'success_count' => $success,
            'fail_count' => $total - $success,
            'success_rate' => $total > 0 ? round($success / $total * 100, 2) : 0,
        ];
    }
}