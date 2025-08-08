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

namespace Motong\OAuth\Service\Admin\System;

use App\Service\Admin\BaseService;
use Motong\OAuth\Model\SysOAuthConfig;
use Motong\OAuth\Model\UserOAuthBind;
use Motong\OAuth\Constants\OAuthPlatformConstants;
use Motong\OAuth\Constants\OAuthClientTypeConstants;
use Motong\OAuth\Service\Provider\WeChatMiniappProvider;
use App\Constants\ErrorCode;
use ZYProSoft\Exception\HyperfCommonException;
use Psr\Container\ContainerInterface;

/**
 * OAuth配置管理服务 - 管理后台
 * 
 * @author Motong OAuth Team
 * @version 1.0.0
 */
class OAuthConfigService extends BaseService
{
    public function __construct(ContainerInterface $container)
    {
        parent::__construct($container);
    }

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
     * 获取配置列表
     * 
     * @param int $page 页码
     * @param int $pageSize 每页数量
     * @param array $filters 过滤条件
     * @return array
     */
    public function getList(int $page = 1, int $pageSize = 10, array $filters = []): array
    {
        $query = SysOAuthConfig::query();
        
        // 应用过滤条件
        if (!empty($filters['platform'])) {
            $query->platform($filters['platform']);
        }
        
        if (!empty($filters['client_type'])) {
            $query->clientType($filters['client_type']);
        }
        
        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }
        
        if (!empty($filters['name'])) {
            $query->where('name', 'like', "%{$filters['name']}%");
        }
        
        // 排序
        $query->ordered();
        
        $total = $query->count();
        $list = $query->forPage($page, $pageSize)->get()->map(function ($item) {
            $item->app_secret = '***'; // 隐藏敏感信息
            $item->message_aeskey = $item->message_aeskey ? '***' : null;
            return $item;
        });

        // 记录操作日志
        $this->addOperationLog();
        
        return [
            'list' => $list,
            'total' => $total,
            'page' => $page,
            'page_size' => $pageSize
        ];
    }

    /**
     * 获取配置详情
     * 
     * @param int $id 配置ID
     * @return array
     */
    public function getDetail(int $id): array
    {
        $config = SysOAuthConfig::find($id);
        
        if (!$config) {
            throw new HyperfCommonException(ErrorCode::RECORD_NOT_EXIST, '配置不存在');
        }
        
        $configArray = $config->toArray();
        // 隐藏敏感信息（详情页面也不显示完整密钥）
        $configArray['app_secret'] = '***';
        $configArray['message_aeskey'] = $config->message_aeskey ? '***' : null;

        // 记录操作日志
        $this->addOperationLog();
        
        return $configArray;
    }

    /**
     * 创建配置
     * 
     * @param array $data 配置数据
     * @return array
     */
    public function create(array $data): array
    {
        // 验证数据
        $this->validateConfigData($data);
        
        // 检查是否已存在相同配置
        $exists = SysOAuthConfig::where('platform', $data['platform'])
            ->where('app_id', $data['app_id'])
            ->exists();
            
        if ($exists) {
            throw new HyperfCommonException(ErrorCode::BUSINESS_ERROR, '该平台的AppID配置已存在');
        }
        
        // 加密敏感信息
        $data['app_secret'] = $this->encryptSecret($data['app_secret']);
        
        if (!empty($data['message_aeskey'])) {
            $data['message_aeskey'] = $this->encryptSecret($data['message_aeskey']);
        }
        
        $config = SysOAuthConfig::create($data);

        // 记录操作日志
        $this->addOperationLog();
        
        return [
            'config' => $config->toArray()
        ];
    }

    /**
     * 更新配置
     * 
     * @param int $id 配置ID
     * @param array $data 更新数据
     * @return bool
     */
    public function update(int $id, array $data): bool
    {
        $config = SysOAuthConfig::find($id);
        
        if (!$config) {
            throw new HyperfCommonException(ErrorCode::RECORD_NOT_EXIST, '配置不存在');
        }
        
        // 验证数据
        $this->validateConfigData($data, $id);
        
        // 如果更新app_secret，需要加密
        if (isset($data['app_secret']) && $data['app_secret'] !== '***') {
            $data['app_secret'] = $this->encryptSecret($data['app_secret']);
        } else {
            // 如果是***，表示不更新密钥
            unset($data['app_secret']);
        }
        
        // 如果更新message_aeskey，需要加密
        if (isset($data['message_aeskey'])) {
            if ($data['message_aeskey'] === '***' || empty($data['message_aeskey'])) {
                unset($data['message_aeskey']);
            } else {
                $data['message_aeskey'] = $this->encryptSecret($data['message_aeskey']);
            }
        }
        
        $result = $config->update($data);

        // 记录操作日志
        $this->addOperationLog();
        
        return $result;
    }

    /**
     * 删除配置
     * 
     * @param int $id 配置ID
     * @return bool
     */
    public function delete(int $id): bool
    {
        $config = SysOAuthConfig::find($id);
        
        if (!$config) {
            throw new HyperfCommonException(ErrorCode::RECORD_NOT_EXIST, '配置不存在');
        }
        
        // 检查是否有用户绑定了此配置
        $bindCount = $config->binds()->where('status', UserOAuthBind::STATUS_BOUND)->count();
        if ($bindCount > 0) {
            throw new HyperfCommonException(ErrorCode::BUSINESS_ERROR, "该配置下还有 {$bindCount} 个用户绑定，无法删除");
        }
        
        $result = $config->delete();

        // 记录操作日志
        $this->addOperationLog();
        
        return $result;
    }

    /**
     * 启用配置
     * 
     * @param int $id 配置ID
     * @return bool
     */
    public function enable(int $id): bool
    {
        $config = SysOAuthConfig::find($id);
        
        if (!$config) {
            throw new HyperfCommonException(ErrorCode::RECORD_NOT_EXIST, '配置不存在');
        }
        
        $result = $config->enable(); 

        // 记录操作日志
        $this->addOperationLog();
        
        return $result;
    }

    /**
     * 禁用配置
     * 
     * @param int $id 配置ID
     * @return bool
     */
    public function disable(int $id): bool
    {
        $config = SysOAuthConfig::find($id);
        
        if (!$config) {
            throw new HyperfCommonException(ErrorCode::RECORD_NOT_EXIST, '配置不存在');
        }
        
        $result = $config->disable();

        // 记录操作日志
        $this->addOperationLog();
        
        return $result;
    }

    /**
     * 测试配置连接
     * 
     * @param int $id 配置ID
     * @return array
     */
    public function testConnection(int $id): array
    {
        $config = SysOAuthConfig::find($id);
        
        if (!$config) {
            throw new HyperfCommonException(ErrorCode::RECORD_NOT_EXIST, '配置不存在');
        }

        try {
            // 根据平台类型进行不同的测试
            switch ($config->platform) {
                case OAuthPlatformConstants::WECHAT_MINIAPP:
                    $result = $this->testWeChatMiniappConnection($config);
                    break;
                    
                default:
                    throw new HyperfCommonException(ErrorCode::BUSINESS_ERROR, '暂不支持该平台的连接测试');
            }

            // 记录操作日志
            $this->addOperationLog();

            return $result;
        } catch (\Exception $e) {
            // 记录操作日志
            $this->addOperationLog();
            
            return [
                'success' => false,
                'message' => $e->getMessage(),
                'test_time' => \Carbon\Carbon::now()->toDateTimeString()
            ];
        }
    }

    /**
     * 获取支持的平台和客户端类型
     * 
     * @return array
     */
    public function getSupportedOptions(): array
    {
        $options = [
            'platforms' => $this->getPlatformOptionsFromDict(),
            'client_types' => $this->getClientTypeOptionsFromDict(),
            'status_options' => $this->getStatusOptionsFromDict(),
            // 保留常量作为备选方案
            'platform_recommendations' => [
                'ecommerce' => OAuthPlatformConstants::getRecommendedPlatforms('ecommerce'),
                'social' => OAuthPlatformConstants::getRecommendedPlatforms('social'),
                'enterprise' => OAuthPlatformConstants::getRecommendedPlatforms('enterprise'),
                'developer' => OAuthPlatformConstants::getRecommendedPlatforms('developer'),
                'universal' => OAuthPlatformConstants::getRecommendedPlatforms('universal'),
            ],
        ];

        // 记录操作日志
        $this->addOperationLog();
        
        return $options;
    }

    /**
     * 从字典获取平台选项
     * 
     * @return array
     */
    private function getPlatformOptionsFromDict(): array
    {
        try {
            $dictData = \App\Model\SysDictData::where('dict_type', 'oauth_platform')
                ->where('status', 1)
                ->orderBy('dict_sort')
                ->get();

            $options = [];
            foreach ($dictData as $item) {
                $options[] = [
                    'key' => $item->dict_value,
                    'name' => $item->dict_label,
                    'class' => $item->list_class ?? 'default'
                ];
            }

            return $options;
        } catch (\Exception $e) {
            // 字典数据获取失败时，回退到常量
            $platforms = OAuthPlatformConstants::getAllPlatforms();
            $options = [];
            foreach ($platforms as $key => $name) {
                $options[] = [
                    'key' => $key,
                    'name' => $name,
                    'class' => 'default'
                ];
            }
            return $options;
        }
    }

    /**
     * 从字典获取客户端类型选项
     * 
     * @return array
     */
    private function getClientTypeOptionsFromDict(): array
    {
        try {
            $dictData = \App\Model\SysDictData::where('dict_type', 'oauth_client_type')
                ->where('status', 1)
                ->orderBy('dict_sort')
                ->get();

            $options = [];
            foreach ($dictData as $item) {
                $options[] = [
                    'key' => $item->dict_value,
                    'name' => $item->dict_label,
                    'class' => $item->list_class ?? 'default'
                ];
            }

            return $options;
        } catch (\Exception $e) {
            // 字典数据获取失败时，回退到常量
            $clientTypes = OAuthClientTypeConstants::getAllClientTypes();
            $options = [];
            foreach ($clientTypes as $key => $name) {
                $options[] = [
                    'key' => $key,
                    'name' => $name,
                    'class' => 'default'
                ];
            }
            return $options;
        }
    }

    /**
     * 从字典获取状态选项
     * 
     * @return array
     */
    private function getStatusOptionsFromDict(): array
    {
        try {
            $dictData = \App\Model\SysDictData::where('dict_type', 'oauth_status')
                ->where('status', 1)
                ->orderBy('dict_sort')
                ->get();

            $options = [];
            foreach ($dictData as $item) {
                $options[] = [
                    'key' => $item->dict_value,
                    'name' => $item->dict_label,
                    'class' => $item->list_class ?? 'default'
                ];
            }

            return $options;
        } catch (\Exception $e) {
            // 字典数据获取失败时，提供默认选项
            return [
                ['key' => '0', 'name' => '禁用', 'class' => 'danger'],
                ['key' => '1', 'name' => '启用', 'class' => 'success']
            ];
        }
    }

    /**
     * 批量操作OAuth配置
     * 
     * @param array $ids ID列表
     * @param string $action 操作类型
     * @return array
     */
    public function batchAction(array $ids, string $action): array
    {
        $successCount = 0;
        $failCount = 0;
        $errors = [];

        foreach ($ids as $id) {
            try {
                switch ($action) {
                    case 'enable':
                        $this->enable((int)$id);
                        break;
                    case 'disable':
                        $this->disable((int)$id);
                        break;
                    case 'delete':
                        $this->delete((int)$id);
                        break;
                }
                $successCount++;
            } catch (\Exception $e) {
                $failCount++;
                $errors[] = "ID {$id}: " . $e->getMessage();
            }
        }

        $message = "批量{$this->getActionText($action)}完成，成功 {$successCount} 个";
        if ($failCount > 0) {
            $message .= "，失败 {$failCount} 个";
        }

        // 记录操作日志
        $this->addOperationLog();

        return [
            'success_count' => $successCount,
            'fail_count' => $failCount,
            'errors' => $errors,
            'message' => $message,
        ];
    }

    /**
     * 验证配置数据
     * 
     * @param array $data 配置数据
     * @param int|null $excludeId 排除的ID（用于更新时）
     */
    private function validateConfigData(array $data, ?int $excludeId = null): void
    {
        // 验证必需字段
        $required = ['name', 'platform', 'client_type', 'app_id'];
        if (!$excludeId) {
            $required[] = 'app_secret'; // 新建时必需
        }
        
        foreach ($required as $field) {
            if (empty($data[$field])) {
                throw new HyperfCommonException(ErrorCode::PARAM_ERROR, "字段 {$field} 不能为空");
            }
        }
        
        // 验证平台类型
        if (!OAuthPlatformConstants::isValidPlatform($data['platform'])) {
            throw new HyperfCommonException(ErrorCode::PARAM_ERROR, '不支持的平台类型');
        }
        
        // 验证客户端类型
        if (!OAuthClientTypeConstants::isValidClientType($data['client_type'])) {
            throw new HyperfCommonException(ErrorCode::PARAM_ERROR, '不支持的客户端类型');
        }
        
        // 检查平台和AppID的唯一性
        if (isset($data['platform']) && isset($data['app_id'])) {
            $query = SysOAuthConfig::where('platform', $data['platform'])
                ->where('app_id', $data['app_id']);
                
            if ($excludeId) {
                $query->where('id', '!=', $excludeId);
            }
            
            if ($query->exists()) {
                throw new HyperfCommonException(ErrorCode::BUSINESS_ERROR, '该平台的AppID配置已存在');
            }
        }
    }

    /**
     * 加密敏感信息
     * 
     * @param string $data 原始数据
     * @return string
     */
    private function encryptSecret(string $data): string
    {
        // 这里应该使用项目的加密方法
        // 例如：return encrypt($data);
        return base64_encode($data); // 临时简单加密
    }

    /**
     * 解密敏感信息
     * 
     * @param string $encryptedData 加密数据
     * @return string
     */
    private function decryptSecret(string $encryptedData): string
    {
        // 这里应该使用项目的解密方法
        // 例如：return decrypt($encryptedData);
        return base64_decode($encryptedData); // 临时简单解密
    }

    /**
     * 测试微信小程序连接
     * 
     * @param SysOAuthConfig $config
     * @return array
     */
    private function testWeChatMiniappConnection(SysOAuthConfig $config): array
    {
        $configArray = $config->toArray();
        $configArray['app_secret'] = $this->decryptSecret($config->app_secret);
        
        // 创建微信小程序提供者实例
        $provider = new WeChatMiniappProvider($configArray);
        
        // 尝试获取访问令牌来测试连接
        $tokenResponse = $provider->getAccessToken();
        
        if (isset($tokenResponse['access_token'])) {
            return [
                'success' => true,
                'message' => '连接测试成功',
                'access_token' => substr($tokenResponse['access_token'], 0, 20) . '...',
                'expires_in' => $tokenResponse['expires_in'],
                'test_time' => \Carbon\Carbon::now()->toDateTimeString()
            ];
        } else {
            return [
                'success' => false,
                'message' => '连接测试失败：无法获取访问令牌',
                'response' => $tokenResponse,
                'test_time' => \Carbon\Carbon::now()->toDateTimeString()
            ];
        }
    }

    /**
     * 获取操作文本
     * 
     * @param string $action 操作类型
     * @return string
     */
    private function getActionText(string $action): string
    {
        $actionTexts = [
            'enable' => '启用',
            'disable' => '禁用',
            'delete' => '删除',
        ];

        return $actionTexts[$action] ?? $action;
    }
}