<?php

declare(strict_types=1);

namespace Motong\OAuth\Commands;

use App\Model\SysApi;
use App\Model\SysMenu;
use App\Model\SysMenuApi;
use Hyperf\Command\Command as HyperfCommand;
use Hyperf\Command\Annotation\Command;
use Hyperf\DbConnection\Db;
use Psr\Container\ContainerInterface;
use ZYProSoft\Log\Log;

/**
 * @Command
 */
class InitPermissionCommand extends HyperfCommand
{
    /**
     * @var ContainerInterface
     */
    protected $container;

    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
        parent::__construct('motong:oauth:init-permission');
    }

    public function configure()
    {
        parent::configure();
        $this->setDescription('初始化OAuth模块的权限数据，包括API和菜单绑定关系');
    }

    public function handle()
    {
        $this->line('开始初始化OAuth模块权限数据...', 'info');
        
        try {
            Db::beginTransaction();
            
            // 初始化OAuth模块的API数据
            $apis = $this->initOAuthApis();
            $this->line('OAuth API初始化完成，共创建 ' . count($apis) . ' 个API', 'info');
            
            // 初始化OAuth菜单和API的绑定关系
            $menuApis = $this->initOAuthMenuApiBindings();
            $this->line('OAuth菜单API绑定完成，共创建 ' . count($menuApis) . ' 个绑定关系', 'info');

            Db::commit();
            $this->line('OAuth模块权限数据初始化成功！', 'info');
            
        } catch (\Throwable $e) {
            Db::rollBack();
            $this->error('OAuth模块权限数据初始化失败：' . $e->getMessage());
            Log::error('OAuth模块权限数据初始化失败' . json_encode([
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]));
        }
    }
    
    /**
     * 初始化OAuth模块的API数据
     */
    private function initOAuthApis(): array
    {
        // 清除现有的OAuth相关API数据
        SysApi::where('api_group', 'oauth')->delete();
        
        // OAuth模块管理端API列表
        $apis = [
            // OAuth配置管理分组
            ['api_name' => 'admin.oauthConfig.getList', 'api_path' => '/admin/oauthConfig/getList', 'api_method' => 'POST', 'api_group' => 'oauth', 'description' => '获取OAuth配置列表', 'status' => 1],
            ['api_name' => 'admin.oauthConfig.getDetail', 'api_path' => '/admin/oauthConfig/getDetail', 'api_method' => 'POST', 'api_group' => 'oauth', 'description' => '获取OAuth配置详情', 'status' => 1],
            ['api_name' => 'admin.oauthConfig.create', 'api_path' => '/admin/oauthConfig/create', 'api_method' => 'POST', 'api_group' => 'oauth', 'description' => '创建OAuth配置', 'status' => 1],
            ['api_name' => 'admin.oauthConfig.update', 'api_path' => '/admin/oauthConfig/update', 'api_method' => 'POST', 'api_group' => 'oauth', 'description' => '更新OAuth配置', 'status' => 1],
            ['api_name' => 'admin.oauthConfig.delete', 'api_path' => '/admin/oauthConfig/delete', 'api_method' => 'POST', 'api_group' => 'oauth', 'description' => '删除OAuth配置', 'status' => 1],
            ['api_name' => 'admin.oauthConfig.enable', 'api_path' => '/admin/oauthConfig/enable', 'api_method' => 'POST', 'api_group' => 'oauth', 'description' => '启用OAuth配置', 'status' => 1],
            ['api_name' => 'admin.oauthConfig.disable', 'api_path' => '/admin/oauthConfig/disable', 'api_method' => 'POST', 'api_group' => 'oauth', 'description' => '禁用OAuth配置', 'status' => 1],
            ['api_name' => 'admin.oauthConfig.testConnection', 'api_path' => '/admin/oauthConfig/testConnection', 'api_method' => 'POST', 'api_group' => 'oauth', 'description' => '测试OAuth配置连接', 'status' => 1],
            ['api_name' => 'admin.oauthConfig.getSupportedOptions', 'api_path' => '/admin/oauthConfig/getSupportedOptions', 'api_method' => 'POST', 'api_group' => 'oauth', 'description' => '获取支持的平台和客户端类型', 'status' => 1],
            ['api_name' => 'admin.oauthConfig.batchAction', 'api_path' => '/admin/oauthConfig/batchAction', 'api_method' => 'POST', 'api_group' => 'oauth', 'description' => '批量操作OAuth配置', 'status' => 1],
        ];
        
        $created = [];
        foreach ($apis as $apiData) {
            $api = SysApi::create($apiData);
            $created[] = $api->api_id;
        }
        
        return $created;
    }
    
    /**
     * 初始化OAuth菜单和API的绑定关系
     */
    private function initOAuthMenuApiBindings(): array
    {
        // 清除现有的OAuth菜单API绑定关系
        $oauthMenuIds = SysMenu::where('perms', 'like', 'system:oauth:%')->pluck('menu_id')->toArray();
        if (!empty($oauthMenuIds)) {
            SysMenuApi::whereIn('menu_id', $oauthMenuIds)->delete();
        }
        
        // OAuth API到菜单权限的映射关系
        $apiToMenuPermissionMapping = [
            // 查询相关API映射到查询权限
            'admin.oauthConfig.getList' => 'system:oauth:query',
            'admin.oauthConfig.getDetail' => 'system:oauth:query',
            'admin.oauthConfig.getSupportedOptions' => 'system:oauth:query',
            
            // 添加相关API映射到添加权限
            'admin.oauthConfig.create' => 'system:oauth:add',
            
            // 修改相关API映射到修改权限
            'admin.oauthConfig.update' => 'system:oauth:edit',
            'admin.oauthConfig.enable' => 'system:oauth:edit',
            'admin.oauthConfig.disable' => 'system:oauth:edit',
            'admin.oauthConfig.batchAction' => 'system:oauth:edit',
            
            // 删除相关API映射到删除权限
            'admin.oauthConfig.delete' => 'system:oauth:remove',
            
            // 测试相关API映射到测试权限
            'admin.oauthConfig.testConnection' => 'system:oauth:test',
        ];
        
        $created = [];
        
        // 获取OAuth模块的API数据
        $oauthApis = SysApi::where('api_group', 'oauth')->where('status', 1)->get();
        
        foreach ($oauthApis as $api) {
            $apiName = $api->api_name;
            
            // 查找对应的菜单权限
            if (isset($apiToMenuPermissionMapping[$apiName])) {
                $permission = $apiToMenuPermissionMapping[$apiName];
                
                // 根据权限标识查找菜单ID
                $menu = SysMenu::where('perms', $permission)->first();
                if ($menu) {
                    // 检查绑定关系是否已存在
                    $exists = SysMenuApi::where('menu_id', $menu->menu_id)
                        ->where('api_id', $api->api_id)
                        ->exists();
                        
                    if (!$exists) {
                        SysMenuApi::create([
                            'menu_id' => $menu->menu_id,
                            'api_id' => $api->api_id,
                        ]);
                        
                        $created[] = [
                            'menu_id' => $menu->menu_id,
                            'menu_name' => $menu->menu_name,
                            'api_id' => $api->api_id,
                            'api_name' => $api->api_name,
                            'permission' => $permission,
                        ];
                    }
                } else {
                    $this->warn("未找到权限为 {$permission} 的菜单，跳过API {$apiName} 的绑定");
                }
            } else {
                $this->warn("API {$apiName} 没有配置对应的菜单权限映射");
            }
        }
        
        return $created;
    }
}
