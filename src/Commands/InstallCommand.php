<?php

declare(strict_types=1);

namespace Motong\OAuth\Commands;

use Hyperf\Command\Command as HyperfCommand;
use Hyperf\Command\Annotation\Command;
use Hyperf\Database\Schema\Schema;
use Hyperf\Database\Schema\Blueprint;
use Hyperf\DbConnection\Db;
use Hyperf\Contract\ConfigInterface;
use Psr\Container\ContainerInterface;
use Symfony\Component\Console\Input\InputOption;
use Carbon\Carbon;
use Exception;
use Motong\OAuth\Constants\OAuthPlatformConstants;
use Motong\OAuth\Constants\OAuthClientTypeConstants;
use App\Model\SysDictData;
use App\Model\SysFieldDict;
use App\Model\SysDictType;
use App\Model\SysMenu;
use App\Model\SysRoleMenu;

/**
 * OAuth模块安装命令
 * 
 * 执行该命令将完成OAuth模块的完整安装，包括：
 * 1. 数据库表创建
 * 2. 字典类型和数据初始化
 * 3. 菜单权限初始化
 * 4. 基础数据插入
 * 
 * @Command
 * @author Motong OAuth Team
 * @version 1.0.0
 */
class InstallCommand extends HyperfCommand
{
    /**
     * @var ContainerInterface
     */
    protected $container;

    /**
     * 支持的数据库表列表
     * @var array
     */
    protected $tables = [
        'sys_oauth_config' => 'createSysOAuthConfigTable',
        'user_oauth_bind' => 'createUserOAuthBindTable',
        'oauth_auth_state' => 'createOAuthAuthStateTable',
        'oauth_login_log' => 'createOAuthLoginLogTable'
    ];

    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
        parent::__construct('motong:oauth:install');
    }

    public function configure()
    {
        parent::configure();
        $this->setDescription('Install OAuth module - create tables, initialize data and permissions');
        $this->addOption('force', 'f', InputOption::VALUE_NONE, '强制重新创建表（将删除现有数据）');
        $this->addOption('skip-tables', null, InputOption::VALUE_NONE, '跳过数据库表创建');
        $this->addOption('skip-menu', null, InputOption::VALUE_NONE, '跳过菜单项初始化');
        $this->addOption('skip-dict', null, InputOption::VALUE_NONE, '跳过字典数据初始化');
        $this->addOption('skip-frontend', null, InputOption::VALUE_NONE, '跳过前端模板文件复制');
    }

    public function handle()
    {
        $this->output->title('🚀 OAuth Module Installation');
        
        try {
            // 步骤1: 创建数据库表
            if (!$this->input->getOption('skip-tables')) {
                $this->createDatabaseTables();
            } else {
                $this->output->writeln('<comment>跳过数据库表创建</comment>');
            }

            // 步骤2: 初始化字典数据
            if (!$this->input->getOption('skip-dict')) {
                $this->initDictData();
            } else {
                $this->output->writeln('<comment>跳过字典数据初始化</comment>');
            }

            // 步骤3: 初始化菜单项
            if (!$this->input->getOption('skip-menu')) {
                $this->initMenuItems();
            } else {
                $this->output->writeln('<comment>跳过菜单项初始化</comment>');
            }

            // 步骤4: 插入示例数据
            $this->insertSampleData();

            // 步骤5: 复制前端资源文件
            if (!$this->input->getOption('skip-frontend')) {
                $this->copyFrontendTemplates();
            } else {
                $this->output->writeln('<comment>跳过前端资源文件复制</comment>');
            }

            $this->output->success('✅ OAuth模块安装完成！');
            $this->printSummary();

        } catch (Exception $e) {
            $this->output->error('❌ OAuth模块安装失败: ' . $e->getMessage());
            $this->output->writeln('<error>错误详情: ' . $e->getTraceAsString() . '</error>');
            return 1;
        }

        return 0;
    }

    /**
     * 创建数据库表
     * 
     * @throws Exception
     */
    protected function createDatabaseTables(): void
    {
        $this->output->section('📊 创建数据库表');

        // 检查现有表
        $existingTables = $this->getExistingTables();
        
        if (!empty($existingTables) && !$this->input->getOption('force')) {
            $this->output->warning('⚠️  以下表已存在: ' . implode(', ', $existingTables));
            
            if (!$this->output->confirm('是否继续执行？(现有表将被跳过)', false)) {
                $this->output->writeln('<comment>用户取消操作</comment>');
                return;
            }
        }

        // 如果使用force选项，删除现有表
        if ($this->input->getOption('force') && !empty($existingTables)) {
            $this->dropExistingTables($existingTables);
        }

        // 按顺序创建表（考虑外键依赖）
        $this->createTablesInOrder();
        
        $this->output->writeln('<info>✅ 数据库表创建完成</info>');
    }

    /**
     * 获取现有表列表
     * 
     * @return array
     */
    protected function getExistingTables(): array
    {
        $existingTables = [];
        
        foreach ($this->tables as $tableName => $method) {
            if (Schema::hasTable($tableName)) {
                $existingTables[] = $tableName;
            }
        }
        
        return $existingTables;
    }

    /**
     * 删除现有表
     * 
     * @param array $tables
     */
    protected function dropExistingTables(array $tables): void
    {
        $this->output->writeln('<comment>🗑️  删除现有表...</comment>');
        
        // 按反向顺序删除表（考虑外键约束）
        $dropOrder = array_reverse($tables);
        
        foreach ($dropOrder as $tableName) {
            Schema::dropIfExists($tableName);
            $this->output->writeln("   - 删除表: {$tableName}");
        }
        
        $this->output->writeln('<info>✅ 现有表删除完成</info>');
    }

    /**
     * 按顺序创建表
     */
    protected function createTablesInOrder(): void
    {
        $createOrder = [
            'sys_oauth_config',      // 主配置表，无依赖
            'oauth_auth_state',      // 授权状态表，依赖配置表
            'user_oauth_bind',       // 绑定表，依赖配置表
            'oauth_login_log'        // 日志表，无外键依赖
        ];

        foreach ($createOrder as $tableName) {
            if (!Schema::hasTable($tableName)) {
                $method = $this->tables[$tableName];
                $this->$method();
                $this->output->writeln("   ✓ 创建表: {$tableName}");
            } else {
                $this->output->writeln("   - 跳过已存在的表: {$tableName}");
            }
        }
    }

    /**
     * 创建OAuth配置表
     */
    protected function createSysOAuthConfigTable(): void
    {
        Schema::create('sys_oauth_config', function (Blueprint $table) {
            $table->bigIncrements('id')->comment('配置ID');
            $table->string('name', 100)->comment('配置名称');
            $table->text('description')->nullable()->comment('配置描述');
            $table->string('platform', 20)->comment('平台类型(见OAuthPlatformConstants)');
            $table->string('client_type', 20)->comment('客户端类型(见OAuthClientTypeConstants)');
            $table->string('app_id', 100)->comment('AppID/ClientID');
            $table->string('app_secret', 200)->comment('AppSecret/ClientSecret(加密存储)');
            $table->string('auth_redirect', 500)->nullable()->comment('授权回调地址');
            $table->string('scopes', 255)->default('snsapi_userinfo')->comment('授权范围');
            $table->string('message_token', 100)->nullable()->comment('消息校验Token(如适用)');
            $table->string('message_aeskey', 100)->nullable()->comment('消息加解密密钥(如适用)');
            $table->json('extra_config')->nullable()->comment('额外配置(JSON格式)');
            $table->tinyInteger('status')->default(1)->comment('状态：1=启用，0=禁用');
            $table->integer('sort_order')->default(0)->comment('排序权重');
            $table->timestamps();
            
            // 索引和约束
            $table->unique(['platform', 'app_id'], 'uk_platform_appid')->comment('同一平台的同一AppID只能存在一个配置');
            $table->index(['platform', 'client_type'], 'idx_platform_client');
            $table->index(['status', 'sort_order'], 'idx_status_sort');
            
            $table->engine = "InnoDB";
            $table->charset = "utf8mb4";
            $table->collation = "utf8mb4_unicode_ci";
        });
    }

    /**
     * 创建用户OAuth绑定表
     */
    protected function createUserOAuthBindTable(): void
    {
        Schema::create('user_oauth_bind', function (Blueprint $table) {
            $table->bigIncrements('bind_id')->comment('绑定ID');
            $table->bigInteger('user_id')->unsigned()->comment('用户ID');
            $table->bigInteger('config_id')->unsigned()->comment('配置ID(关联sys_oauth_config)');
            $table->string('platform', 20)->comment('平台类型');
            $table->string('openid', 128)->comment('第三方平台用户唯一标识');
            $table->string('unionid', 128)->nullable()->comment('第三方平台用户UnionID(如微信)');
            $table->string('nickname', 100)->nullable()->comment('第三方平台昵称');
            $table->string('avatar', 500)->nullable()->comment('第三方平台头像URL');
            $table->tinyInteger('gender')->nullable()->comment('性别：0=未知，1=男，2=女');
            $table->string('country', 50)->nullable()->comment('国家');
            $table->string('province', 50)->nullable()->comment('省份');
            $table->string('city', 50)->nullable()->comment('城市');
            $table->json('oauth_info')->nullable()->comment('第三方平台完整用户信息(JSON)');
            $table->text('access_token')->nullable()->comment('访问令牌(加密存储)');
            $table->text('refresh_token')->nullable()->comment('刷新令牌(加密存储)');
            $table->integer('expires_in')->nullable()->comment('token过期时间(秒)');
            $table->timestamp('token_expires_at')->nullable()->comment('token过期时间点');
            $table->timestamp('bind_time')->default(Db::raw('CURRENT_TIMESTAMP'))->comment('绑定时间');
            $table->timestamp('last_login_time')->nullable()->comment('最后登录时间');
            $table->integer('login_count')->default(0)->comment('登录次数统计');
            $table->tinyInteger('status')->default(1)->comment('绑定状态：1=正常，0=解绑');
            $table->timestamps();
            
            // 索引和约束
            $table->unique(['config_id', 'openid'], 'uk_config_openid');
            $table->unique(['user_id', 'config_id'], 'uk_user_config')->comment('一个用户在同一个具体配置下只能有一个绑定');
            $table->index(['platform', 'openid'], 'idx_platform_openid');
            $table->index(['user_id', 'platform'], 'idx_user_platform');
            $table->index('unionid', 'idx_unionid');
            $table->index('user_id', 'idx_user_id');
            $table->index('status', 'idx_status');
            $table->index('last_login_time', 'idx_last_login');
            
            // 外键约束
            $table->foreign('config_id', 'fk_user_oauth_bind_config')
                  ->references('id')
                  ->on('sys_oauth_config')
                  ->onDelete('cascade');
            
            $table->engine = "InnoDB";
            $table->charset = "utf8mb4";
            $table->collation = "utf8mb4_unicode_ci";
        });
    }

    /**
     * 创建OAuth授权状态记录表
     */
    protected function createOAuthAuthStateTable(): void
    {
        Schema::create('oauth_auth_state', function (Blueprint $table) {
            $table->bigIncrements('id')->comment('记录ID');
            $table->string('state', 64)->comment('OAuth state参数');
            $table->bigInteger('config_id')->unsigned()->comment('配置ID(关联sys_oauth_config)');
            $table->string('platform', 20)->comment('平台类型');
            $table->string('client_type', 20)->comment('客户端类型');
            $table->string('action', 20)->comment('操作类型：login=登录，bind=绑定');
            $table->bigInteger('user_id')->unsigned()->nullable()->comment('用户ID(绑定操作时必需)');
            $table->string('redirect_url', 500)->nullable()->comment('登录成功后的跳转地址');
            $table->json('extra_data')->nullable()->comment('额外数据');
            $table->string('ip_address', 45)->comment('请求IP地址');
            $table->string('user_agent', 500)->nullable()->comment('用户代理');
            $table->timestamp('expires_at')->comment('过期时间');
            $table->timestamp('used_at')->nullable()->comment('使用时间');
            $table->timestamp('created_at')->nullable();
            
            // 索引和约束
            $table->unique('state', 'uk_state');
            $table->index('expires_at', 'idx_expires_at');
            $table->index('user_id', 'idx_user_id');
            $table->index('config_id', 'idx_config_id');
            $table->index(['platform', 'action'], 'idx_platform_action');
            
            // 外键约束
            $table->foreign('config_id', 'fk_oauth_auth_state_config')
                  ->references('id')
                  ->on('sys_oauth_config')
                  ->onDelete('cascade');
            
            $table->engine = "InnoDB";
            $table->charset = "utf8mb4";
            $table->collation = "utf8mb4_unicode_ci";
        });
    }

    /**
     * 创建OAuth登录日志表
     */
    protected function createOAuthLoginLogTable(): void
    {
        Schema::create('oauth_login_log', function (Blueprint $table) {
            $table->bigIncrements('log_id')->comment('日志ID');
            $table->bigInteger('user_id')->unsigned()->nullable()->comment('用户ID');
            $table->string('platform', 20)->comment('平台类型');
            $table->string('openid', 64)->comment('第三方openid');
            $table->string('action', 20)->comment('操作类型：login=登录，bind=绑定，unbind=解绑');
            $table->string('client_type', 20)->comment('客户端类型');
            $table->string('result', 20)->comment('结果：success=成功，fail=失败');
            $table->string('error_message', 500)->nullable()->comment('错误信息');
            $table->string('ip_address', 45)->comment('IP地址');
            $table->string('user_agent', 500)->nullable()->comment('用户代理');
            $table->json('request_data')->nullable()->comment('请求数据');
            $table->json('response_data')->nullable()->comment('响应数据');
            $table->timestamp('created_at')->nullable();
            
            // 索引
            $table->index('user_id', 'idx_user_id');
            $table->index('platform', 'idx_platform');
            $table->index('result', 'idx_result');
            $table->index('created_at', 'idx_created_at');
            
            $table->engine = "InnoDB";
            $table->charset = "utf8mb4";
            $table->collation = "utf8mb4_unicode_ci";
        });
    }

    /**
     * 初始化字典数据
     */
    protected function initDictData(): void
    {
        $this->output->section('📝 初始化字典数据');
        
        // 创建字典类型和数据
        $this->createDictTypeAndData();
        
        // 绑定字段与字典类型
        $this->fieldBindDictType();
        
        $this->output->writeln('<info>✅ 字典数据初始化完成</info>');
    }

    /**
     * 创建字典类型和数据
     */
    protected function createDictTypeAndData(): void
    {
        $this->output->writeln('<info>📝 插入字典类型和数据...</info>');
        
        // 创建字典类型
        $this->createDictTypes();
        
        // 创建字典数据
        $this->createDictData();
    }

    /**
     * 创建字典类型
     */
    private function createDictTypes(): void
    {
        $dictTypes = [
            [
                'dict_name' => 'OAuth平台类型',
                'dict_type' => 'oauth_platform',
                'status' => 1,
                'is_system' => 1,
                'remark' => 'OAuth第三方登录平台类型字典'
            ],
            [
                'dict_name' => 'OAuth客户端类型', 
                'dict_type' => 'oauth_client_type',
                'status' => 1,
                'is_system' => 1,
                'remark' => 'OAuth客户端类型字典'
            ],
            [
                'dict_name' => 'OAuth配置状态',
                'dict_type' => 'oauth_status', 
                'status' => 1,
                'is_system' => 1,
                'remark' => 'OAuth配置启用状态字典'
            ]
        ];

        foreach ($dictTypes as $dictType) {
            // 检查是否已存在
            $exists = SysDictType::where('dict_type', $dictType['dict_type'])->exists();
            if (!$exists) {
                SysDictType::create($dictType);
                $this->output->writeln("  - 创建字典类型: {$dictType['dict_name']} ({$dictType['dict_type']})");
            } else {
                $this->output->writeln("  - 字典类型已存在: {$dictType['dict_name']} ({$dictType['dict_type']})");
            }
        }
    }

    /**
     * 创建字典数据
     */
    private function createDictData(): void
    {
        // 创建OAuth平台类型字典数据
        $this->createOAuthPlatformDictData();
        
        // 创建OAuth客户端类型字典数据  
        $this->createOAuthClientTypeDictData();
        
        // 创建OAuth状态字典数据
        $this->createOAuthStatusDictData();

        // 创建系统模块OAuth字典数据
        $this->insertSystemModuleDictData();
    }

    private function insertSystemModuleDictData() 
    {
        $dictData = [
            'dict_sort' => 1,
            'dict_label' => 'OAuth配置',
            'dict_value' => 'oauthConfig',
            'dict_type' => 'sys_module',
            'css_class' => '',
            'status' => 1,
            'remark' => 'OAuth配置模块'
        ];

        $exists = SysDictData::where('dict_type', 'sys_module')
            ->where('dict_value', $dictData['dict_value'])
            ->exists();

        if (!$exists) {
            SysDictData::create($dictData);
        }
    }

    /**
     * 创建OAuth平台类型字典数据
     */
    private function createOAuthPlatformDictData(): void
    {
        $platforms = OAuthPlatformConstants::getAllPlatforms();
        
        $sort = 1;
        foreach ($platforms as $value => $label) {
            $dictData = [
                'dict_sort' => $sort++,
                'dict_label' => $label,
                'dict_value' => $value,
                'dict_type' => 'oauth_platform',
                'css_class' => '',
                'list_class' => $this->getPlatformListClass($value),
                'status' => 1,
                'remark' => "OAuth平台: {$label}"
            ];

            // 检查是否已存在
            $exists = SysDictData::where('dict_type', 'oauth_platform')
                ->where('dict_value', $value)
                ->exists();
                
            if (!$exists) {
                SysDictData::create($dictData);
                $this->output->writeln("  - 创建平台字典数据: {$label} ({$value})");
            }
        }
    }

    /**
     * 创建OAuth客户端类型字典数据
     */
    private function createOAuthClientTypeDictData(): void
    {
        $clientTypes = OAuthClientTypeConstants::getAllClientTypes();
        
        $sort = 1;
        foreach ($clientTypes as $value => $label) {
            $dictData = [
                'dict_sort' => $sort++,
                'dict_label' => $label,
                'dict_value' => $value,
                'dict_type' => 'oauth_client_type',
                'css_class' => '',
                'list_class' => $this->getClientTypeListClass($value),
                'status' => 1,
                'remark' => "OAuth客户端类型: {$label}"
            ];

            // 检查是否已存在
            $exists = SysDictData::where('dict_type', 'oauth_client_type')
                ->where('dict_value', $value)
                ->exists();
                
            if (!$exists) {
                SysDictData::create($dictData);
                $this->output->writeln("  - 创建客户端类型字典数据: {$label} ({$value})");
            }
        }
    }

    /**
     * 创建OAuth状态字典数据
     */
    private function createOAuthStatusDictData(): void
    {
        $statusList = [
            ['value' => '0', 'label' => '禁用', 'class' => 'danger'],
            ['value' => '1', 'label' => '启用', 'class' => 'success']
        ];
        
        $sort = 1;
        foreach ($statusList as $status) {
            $dictData = [
                'dict_sort' => $sort++,
                'dict_label' => $status['label'],
                'dict_value' => $status['value'],
                'dict_type' => 'oauth_status',
                'css_class' => '',
                'list_class' => $status['class'],
                'status' => 1,
                'remark' => "OAuth配置状态: {$status['label']}"
            ];

            // 检查是否已存在
            $exists = SysDictData::where('dict_type', 'oauth_status')
                ->where('dict_value', $status['value'])
                ->exists();
                
            if (!$exists) {
                SysDictData::create($dictData);
                $this->output->writeln("  - 创建状态字典数据: {$status['label']} ({$status['value']})");
            }
        }
    }

    /**
     * 获取平台对应的样式类
     */
    private function getPlatformListClass(string $platform): string
    {
        $classMap = [
            'wechat_mp' => 'success',
            'wechat_miniapp' => 'success', 
            'wechat_open' => 'success',
            'wework' => 'success',
            'alipay_web' => 'warning',
            'alipay_miniapp' => 'warning',
            'alipay_app' => 'warning',
            'qq_web' => 'info',
            'qq_app' => 'info',
            'weibo' => 'danger',
            'github' => 'primary',
            'gitlab' => 'primary',
            'gitee' => 'primary'
        ];
        
        return $classMap[$platform] ?? 'default';
    }

    /**
     * 获取客户端类型对应的样式类
     */
    private function getClientTypeListClass(string $clientType): string
    {
        $classMap = [
            'web' => 'primary',
            'miniapp' => 'success',
            'app' => 'warning', 
            'desktop' => 'info'
        ];
        
        return $classMap[$clientType] ?? 'default';
    }

    /**
     * 绑定字段与字典类型
     */
    protected function fieldBindDictType(): void
    {
        $this->output->writeln('<info>🔗 绑定字段与字典类型...</info>');
        
        // 定义字段与字典类型的绑定关系
        $fieldBindings = [
            [
                'table_name' => 'sys_oauth_config',
                'field_name' => 'platform',
                'dict_type' => 'oauth_platform',
                'description' => 'OAuth配置表平台类型字段绑定',
            ],
            [
                'table_name' => 'sys_oauth_config',
                'field_name' => 'client_type',
                'dict_type' => 'oauth_client_type',
                'description' => 'OAuth配置表客户端类型字段绑定',
            ],
            [
                'table_name' => 'sys_oauth_config',
                'field_name' => 'status',
                'dict_type' => 'oauth_status',
                'description' => 'OAuth配置表状态字段绑定',
            ]
        ];

        foreach ($fieldBindings as $binding) {
            // 检查绑定是否已存在
            $exists = SysFieldDict::where('table_name', $binding['table_name'])
                ->where('field_name', $binding['field_name'])
                ->exists();
                
            if (!$exists) {
                SysFieldDict::create($binding);
                $this->output->writeln("  - 创建字段绑定: {$binding['table_name']}.{$binding['field_name']} → {$binding['dict_type']}");
            } else {
                $this->output->writeln("  - 字段绑定已存在: {$binding['table_name']}.{$binding['field_name']}");
            }
        }
        
        $this->output->writeln('<info>✅ 字段与字典类型绑定完成</info>');
    }

    /**
     * 初始化菜单项
     */
    protected function initMenuItems(): void
    {
        $this->output->section('📋 初始化菜单项');
        
        // 获取系统管理菜单ID，作为平台配置的父菜单
        $systemMenuId = $this->getSystemMenuId();
        
        // 创建菜单数据
        $menus = $this->buildMenuData($systemMenuId);
        
        // 插入菜单
        $this->insertMenus($menus);
        
        // 为管理员角色分配菜单权限
        $this->assignMenuPermissions();
        
        $this->output->writeln('<info>✅ 菜单项初始化完成</info>');
    }

    /**
     * 获取系统管理菜单ID
     */
    protected function getSystemMenuId(): int
    {
        $systemMenu = SysMenu::where('menu_name', '系统管理')
            ->where('menu_type', 'M')
            ->first();
        
        return $systemMenu->menu_id;
    }

    /**
     * 构建菜单数据
     */
    protected function buildMenuData(int $parentId): array
    {
        // 获取下一个可用的菜单ID
        $maxMenuId = SysMenu::max('menu_id') ?? 0;
        $nextMenuId = $maxMenuId + 1;

        return [
            // 平台配置页面菜单
            [
                'menu_id' => $nextMenuId,
                'menu_name' => '平台授权',
                'parent_id' => $parentId,
                'order_num' => 10,
                'path' => 'oauth',
                'component' => 'system/oauth/index',
                'is_frame' => 1,
                'is_cache' => 1,
                'menu_type' => 'C',
                'visible' => 1,
                'status' => 1,
                'perms' => 'system:oauth:list',
                'icon' => 'el-icon-connection',
                'remark' => 'OAuth平台配置管理'
            ],
            // 平台配置查询按钮
            [
                'menu_id' => $nextMenuId + 1,
                'menu_name' => '平台配置查询',
                'parent_id' => $nextMenuId,
                'order_num' => 1,
                'path' => '',
                'component' => '',
                'is_frame' => 1,
                'is_cache' => 0,
                'menu_type' => 'F',
                'visible' => 1,
                'status' => 1,
                'perms' => 'system:oauth:query',
                'icon' => '#',
                'remark' => '平台配置查询权限'
            ],
            // 平台配置添加按钮
            [
                'menu_id' => $nextMenuId + 2,
                'menu_name' => '平台配置添加',
                'parent_id' => $nextMenuId,
                'order_num' => 2,
                'path' => '',
                'component' => '',
                'is_frame' => 1,
                'is_cache' => 0,
                'menu_type' => 'F',
                'visible' => 1,
                'status' => 1,
                'perms' => 'system:oauth:add',
                'icon' => '#',
                'remark' => '平台配置添加权限'
            ],
            // 平台配置修改按钮
            [
                'menu_id' => $nextMenuId + 3,
                'menu_name' => '平台配置修改',
                'parent_id' => $nextMenuId,
                'order_num' => 3,
                'path' => '',
                'component' => '',
                'is_frame' => 1,
                'is_cache' => 0,
                'menu_type' => 'F',
                'visible' => 1,
                'status' => 1,
                'perms' => 'system:oauth:edit',
                'icon' => '#',
                'remark' => '平台配置修改权限'
            ],
            // 平台配置删除按钮
            [
                'menu_id' => $nextMenuId + 4,
                'menu_name' => '平台配置删除',
                'parent_id' => $nextMenuId,
                'order_num' => 4,
                'path' => '',
                'component' => '',
                'is_frame' => 1,
                'is_cache' => 0,
                'menu_type' => 'F',
                'visible' => 1,
                'status' => 1,
                'perms' => 'system:oauth:remove',
                'icon' => '#',
                'remark' => '平台配置删除权限'
            ],
            // 平台配置测试按钮
            [
                'menu_id' => $nextMenuId + 5,
                'menu_name' => '平台配置测试',
                'parent_id' => $nextMenuId,
                'order_num' => 5,
                'path' => '',
                'component' => '',
                'is_frame' => 1,
                'is_cache' => 0,
                'menu_type' => 'F',
                'visible' => 1,
                'status' => 1,
                'perms' => 'system:oauth:test',
                'icon' => '#',
                'remark' => '平台配置测试权限'
            ]
        ];
    }

    /**
     * 插入菜单
     */
    protected function insertMenus(array $menus): void
    {
        foreach ($menus as $menuData) {
            $exists = SysMenu::where('menu_id', $menuData['menu_id'])->exists();
            
            if (!$exists) {
                SysMenu::create($menuData);
                $type = $menuData['menu_type'] === 'C' ? '页面' : '按钮';
                $this->output->writeln("  - 创建{$type}菜单: {$menuData['menu_name']} (权限: {$menuData['perms']})");
            } else {
                $this->output->writeln("  - 菜单已存在: {$menuData['menu_name']}");
            }
        }
    }

    /**
     * 为管理员角色分配菜单权限
     */
    protected function assignMenuPermissions(): void
    {
        $this->output->writeln('<info>🔑 分配菜单权限给管理员角色...</info>');
        
        // 获取新创建的菜单ID
        $oauthMenuIds = SysMenu::where('perms', 'like', 'system:oauth:%')->pluck('menu_id')->toArray();
        
        if (empty($oauthMenuIds)) {
            $this->output->writeln('<comment>未找到OAuth相关菜单，跳过权限分配</comment>');
            return;
        }
        
        // 为系统管理员角色(role_id=2)分配权限
        $adminRoleId = 2;
        
        foreach ($oauthMenuIds as $menuId) {
            $exists = SysRoleMenu::where('role_id', $adminRoleId)
                ->where('menu_id', $menuId)
                ->exists();
                
            if (!$exists) {
                SysRoleMenu::create([
                    'role_id' => $adminRoleId,
                    'menu_id' => $menuId
                ]);
            }
        }
        
        $this->output->writeln("  - 为系统管理员分配了 " . count($oauthMenuIds) . " 个菜单权限");
    }

    /**
     * 插入示例数据
     */
    protected function insertSampleData(): void
    {
        $this->output->section('📋 插入示例数据');
        
        // 插入微信小程序配置示例
        $exists = Db::table('sys_oauth_config')
            ->where('platform', 'wechat_miniapp')
            ->where('app_id', 'your_miniapp_appid')
            ->exists();
            
        if (!$exists) {
            Db::table('sys_oauth_config')->insert([
                'name' => '微信小程序登录',
                'description' => '用于小程序端的微信登录配置',
                'platform' => 'wechat_miniapp',
                'client_type' => 'miniapp',
                'app_id' => 'your_miniapp_appid',
                'app_secret' => base64_encode('your_miniapp_secret'), // 简单加密
                'scopes' => 'snsapi_userinfo',
                'status' => 1,
                'sort_order' => 100,
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now()
            ]);
            
            $this->output->writeln('  ✓ 插入微信小程序配置示例');
        } else {
            $this->output->writeln('  - 示例数据已存在，跳过插入');
        }
    }

    /**
     * 复制前端资源文件
     * @return void
     */
    protected function copyFrontendTemplates(): void
    {
        $this->output->section('📋 复制前端模板');

        $adminWebDir = $this->container->get(ConfigInterface::class)->get('hyperf-common.admin_web');
        
        // 如果未配置前端项目路径，跳过复制
        if (empty($adminWebDir)) {
            $this->output->writeln('<comment>⚠️  未配置前端项目路径(ADMIN_WEB_DIR_PATH)，跳过前端模板复制</comment>');
            $this->output->writeln('<comment>如需复制前端模板，请设置环境变量 ADMIN_WEB_DIR_PATH</comment>');
            return;
        }
        
        // 检查前端项目目录是否存在
        if (!is_dir($adminWebDir)) {
            $this->output->writeln("<comment>⚠️  前端项目目录不存在: {$adminWebDir}</comment>");
            $this->output->writeln('<comment>跳过前端模板复制</comment>');
            return;
        }

        $apiDir = $adminWebDir . '/src/api/system/oauth.js';
        $viewDir = $adminWebDir . '/src/views/system/oauth/index.vue';

        // 检查目标文件是否已存在
        $apiExists = file_exists($apiDir);
        $viewExists = file_exists($viewDir);

        if ($apiExists && $viewExists) {
            $this->output->writeln('  ✓ 前端模板已存在，跳过复制');
            return;
        }

        $oauthDir = $adminWebDir . '/src/views/system/oauth';
        if (!file_exists($oauthDir)) {
            mkdir($oauthDir, 0755, true);
        }

        // 定义源文件路径
        $sourceApiFile = __DIR__ . '/../assets/api/oauth.js';
        $sourceViewFile = __DIR__ . '/../assets/views/oauth/index.vue';
        
        if (!file_exists($sourceApiFile) || !file_exists($sourceViewFile)) {
            $this->output->writeln('<error>⚠️  源文件缺失，无法复制前端模板</error>');
            return;
        }

        // 复制API文件
        if (!$apiExists) {
            $targetApiDir = dirname($apiDir);
            if (!file_exists($targetApiDir)) {
                mkdir($targetApiDir, 0755, true);
            }
            
            if (copy($sourceApiFile, $apiDir)) {
                $this->output->writeln("  ✓ 复制API文件: oauth.js");
            } else {
                $this->output->writeln("  ✗ 复制API文件失败");
                return;
            }
        }
        
        // 复制视图文件
        if (!$viewExists) {
            if (copy($sourceViewFile, $viewDir)) {
                $this->output->writeln("  ✓ 复制视图文件: index.vue");
            } else {
                $this->output->writeln("  ✗ 复制视图文件失败");
                return;
            }
        }
        
        $this->output->writeln('<info>✅ 前端模板复制完成</info>');
    }

    /**
     * 打印安装总结
     */
    protected function printSummary(): void
    {
        $this->output->section('📊 安装总结');
        
        $this->output->writeln('已创建的数据库表:');
        foreach ($this->tables as $tableName => $method) {
            $this->output->writeln("  ✓ {$tableName}");
        }
        
        $this->output->writeln('');
        $this->output->writeln('已初始化的功能:');
        $this->output->writeln('  ✓ OAuth平台类型字典数据');
        $this->output->writeln('  ✓ OAuth客户端类型字典数据');
        $this->output->writeln('  ✓ OAuth配置状态字典数据');
        $this->output->writeln('  ✓ 平台配置菜单和权限');
        $this->output->writeln('  ✓ 示例配置数据');
        
        $this->output->writeln('');
        $this->output->writeln('下一步操作建议:');
        $this->output->writeln('  1. 更新 sys_oauth_config 表中的实际配置信息');
        $this->output->writeln('  2. 在管理后台访问"系统管理 -> 平台配置"页面');
        $this->output->writeln('  3. 测试OAuth配置的连接状态');
        $this->output->writeln('  4. 实现具体的第三方平台登录流程');
        
        $this->output->writeln('');
        $this->output->writeln('<info>🎉 OAuth模块已成功安装，可以开始使用了！</info>');
    }
}
