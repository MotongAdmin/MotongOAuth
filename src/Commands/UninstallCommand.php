<?php

declare(strict_types=1);

namespace Motong\OAuth\Commands;

use Hyperf\Command\Command as HyperfCommand;
use Hyperf\Command\Annotation\Command;
use Hyperf\Database\Schema\Schema;
use Hyperf\DbConnection\Db;
use Hyperf\Contract\ConfigInterface;
use Psr\Container\ContainerInterface;
use Symfony\Component\Console\Input\InputOption;
use Exception;
use App\Model\SysDictData;
use App\Model\SysFieldDict;
use App\Model\SysDictType;
use App\Model\SysMenu;
use App\Model\SysRoleMenu;
use App\Model\SysApi;
use App\Model\SysMenuApi;

/**
 * OAuth模块卸载命令
 * 
 * 执行该命令将完全清理OAuth模块的所有数据，包括：
 * 1. 删除数据库表
 * 2. 清理字典类型和数据
 * 3. 清理菜单权限
 * 4. 清理相关配置数据
 * 
 * @Command
 * @author Motong OAuth Team
 * @version 1.0.0
 */
class UninstallCommand extends HyperfCommand
{
    /**
     * @var ContainerInterface
     */
    protected $container;

    /**
     * OAuth模块相关的数据库表（按删除顺序排列）
     * @var array
     */
    protected $tables = [
        'oauth_login_log',          // 无外键依赖，先删除
        'user_oauth_bind',          // 依赖 sys_oauth_config
        'oauth_auth_state',         // 依赖 sys_oauth_config
        'sys_oauth_config'          // 主表，最后删除
    ];

    /**
     * OAuth模块相关的字典类型
     * @var array
     */
    protected $dictTypes = [
        'oauth_platform',
        'oauth_client_type',
        'oauth_status'
    ];

    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
        parent::__construct('motong:oauth:uninstall');
    }

    public function configure()
    {
        parent::configure();
        $this->setDescription('Uninstall OAuth module - remove all tables, data and permissions');
        $this->addOption('force', 'f', InputOption::VALUE_NONE, '强制卸载，不询问确认');
        $this->addOption('skip-tables', null, InputOption::VALUE_NONE, '跳过数据库表删除');
        $this->addOption('skip-dict', null, InputOption::VALUE_NONE, '跳过字典数据清理');
        $this->addOption('skip-menu', null, InputOption::VALUE_NONE, '跳过菜单权限清理');
        $this->addOption('skip-frontend', null, InputOption::VALUE_NONE, '跳过前端文件清理');
        $this->addOption('skip-permission', null, InputOption::VALUE_NONE, '跳过权限数据清理');
        $this->addOption('backup', 'b', InputOption::VALUE_NONE, '卸载前备份数据');
    }

    public function handle()
    {
        $this->output->title('🗑️ OAuth Module Uninstallation');
        
        // 确认卸载操作
        if (!$this->confirmUninstall()) {
            $this->output->writeln('<comment>用户取消卸载操作</comment>');
            return 0;
        }

        try {
            // 步骤1: 数据备份（可选）
            if ($this->input->getOption('backup')) {
                $this->backupData();
            }

            // 步骤2: 清理菜单权限
            if (!$this->input->getOption('skip-menu')) {
                $this->cleanupMenuPermissions();
            } else {
                $this->output->writeln('<comment>跳过菜单权限清理</comment>');
            }

            // 步骤3: 清理字典数据
            if (!$this->input->getOption('skip-dict')) {
                $this->cleanupDictData();
            } else {
                $this->output->writeln('<comment>跳过字典数据清理</comment>');
            }

            // 步骤4: 清理前端文件
            if (!$this->input->getOption('skip-frontend')) {
                $this->cleanupFrontendFiles();
            } else {
                $this->output->writeln('<comment>跳过前端文件清理</comment>');
            }

            // 步骤5: 清理权限数据
            if (!$this->input->getOption('skip-permission')) {
                $this->cleanupPermissionData();
            } else {
                $this->output->writeln('<comment>跳过权限数据清理</comment>');
            }

            // 步骤6: 删除数据库表
            if (!$this->input->getOption('skip-tables')) {
                $this->dropDatabaseTables();
            } else {
                $this->output->writeln('<comment>跳过数据库表删除</comment>');
            }

            $this->output->success('✅ OAuth模块卸载完成！');
            $this->printUninstallSummary();

        } catch (Exception $e) {
            $this->output->error('❌ OAuth模块卸载失败: ' . $e->getMessage());
            $this->output->writeln('<error>错误详情: ' . $e->getTraceAsString() . '</error>');
            return 1;
        }

        return 0;
    }

    /**
     * 确认卸载操作
     * 
     * @return bool
     */
    protected function confirmUninstall(): bool
    {
        if ($this->input->getOption('force')) {
            return true;
        }

        $this->output->warning('⚠️  警告：此操作将完全删除OAuth模块的所有数据！');
        $this->output->writeln('');
        $this->output->writeln('将要执行的操作：');
        $this->output->writeln('  • 删除OAuth相关数据库表及其所有数据');
        $this->output->writeln('  • 清理OAuth相关字典类型和数据');
        $this->output->writeln('  • 清理OAuth相关菜单和权限配置');
        $this->output->writeln('  • 清理字段字典绑定关系');
        if (!$this->input->getOption('skip-frontend')) {
            $this->output->writeln('  • 删除前端OAuth文件和目录');
        }
        $this->output->writeln('');
        
        return $this->output->confirm('确认要继续卸载OAuth模块吗？', false);
    }

    /**
     * 备份数据
     */
    protected function backupData(): void
    {
        $this->output->section('💾 备份数据');
        
        $backupDir = BASE_PATH . '/storage/backup/oauth_' . date('Y-m-d_H-i-s');
        
        if (!is_dir(dirname($backupDir))) {
            mkdir(dirname($backupDir), 0755, true);
        }
        mkdir($backupDir, 0755, true);

        foreach ($this->tables as $table) {
            if (Schema::hasTable($table)) {
                $this->backupTable($table, $backupDir);
            }
        }
        
        // 备份相关字典数据
        $this->backupDictData($backupDir);
        
        // 备份菜单数据
        $this->backupMenuData($backupDir);
        
        // 备份前端文件
        $this->backupFrontendFiles($backupDir);
        
        $this->output->writeln("<info>✅ 数据备份完成，备份目录: {$backupDir}</info>");
    }

    /**
     * 备份单个表
     */
    protected function backupTable(string $table, string $backupDir): void
    {
        $data = Db::table($table)->get()->toArray();
        
        if (!empty($data)) {
            $backupFile = $backupDir . "/{$table}.json";
            file_put_contents($backupFile, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            $this->output->writeln("  - 备份表 {$table}: " . count($data) . " 条记录");
        }
    }

    /**
     * 备份字典数据
     */
    protected function backupDictData(string $backupDir): void
    {
        $dictData = [];
        
        // 备份字典类型
        $dictTypes = SysDictType::whereIn('dict_type', $this->dictTypes)->get()->toArray();
        if (!empty($dictTypes)) {
            $dictData['dict_types'] = $dictTypes;
        }
        
        // 备份字典数据
        $dictValues = SysDictData::whereIn('dict_type', $this->dictTypes)->get()->toArray();
        if (!empty($dictValues)) {
            $dictData['dict_data'] = $dictValues;
        }
        
        // 备份字段绑定
        $fieldBindings = SysFieldDict::where('table_name', 'sys_oauth_config')->get()->toArray();
        if (!empty($fieldBindings)) {
            $dictData['field_bindings'] = $fieldBindings;
        }
        
        if (!empty($dictData)) {
            $backupFile = $backupDir . '/dict_data.json';
            file_put_contents($backupFile, json_encode($dictData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            $this->output->writeln("  - 备份字典数据: " . count($dictData) . " 类");
        }
    }

    /**
     * 备份菜单数据
     */
    protected function backupMenuData(string $backupDir): void
    {
        $menuData = [];
        
        // 获取OAuth相关菜单
        $oauthMenus = SysMenu::where('perms', 'like', 'system:oauth:%')->get()->toArray();
        if (!empty($oauthMenus)) {
            $menuData['menus'] = $oauthMenus;
            
            // 获取角色菜单关联
            $menuIds = array_column($oauthMenus, 'menu_id');
            $roleMenus = SysRoleMenu::whereIn('menu_id', $menuIds)->get()->toArray();
            if (!empty($roleMenus)) {
                $menuData['role_menus'] = $roleMenus;
            }
        }
        
        if (!empty($menuData)) {
            $backupFile = $backupDir . '/menu_data.json';
            file_put_contents($backupFile, json_encode($menuData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            $this->output->writeln("  - 备份菜单数据: " . count($oauthMenus) . " 个菜单");
        }
    }

    /**
     * 清理菜单权限
     */
    protected function cleanupMenuPermissions(): void
    {
        $this->output->section('🔑 清理菜单权限');
        
        // 获取OAuth相关菜单
        $oauthMenus = SysMenu::where('perms', 'like', 'system:oauth:%')->withTrashed()->get();
        
        if ($oauthMenus->isEmpty()) {
            $this->output->writeln('<comment>未找到OAuth相关菜单</comment>');
            return;
        }
        
        $menuIds = $oauthMenus->pluck('menu_id')->toArray();
        
        // 1. 删除角色菜单关联
        $roleMenuCount = SysRoleMenu::whereIn('menu_id', $menuIds)->count();
        if ($roleMenuCount > 0) {
            SysRoleMenu::whereIn('menu_id', $menuIds)->delete();
            $this->output->writeln("  - 删除角色菜单关联: {$roleMenuCount} 条");
        }
        
        // 2. 删除菜单项（按钮菜单 -> 页面菜单）
        $buttonMenus = $oauthMenus->where('menu_type', 'F');
        $pageMenus = $oauthMenus->where('menu_type', 'C');
        
        // 先删除按钮菜单
        foreach ($buttonMenus as $menu) {
            $menu->forceDelete();
            $this->output->writeln("  - 删除按钮菜单: {$menu->menu_name} ({$menu->perms})");
        }
        
        // 再删除页面菜单
        foreach ($pageMenus as $menu) {
            $menu->forceDelete();
            $this->output->writeln("  - 删除页面菜单: {$menu->menu_name} ({$menu->perms})");
        }
        
        $this->output->writeln('<info>✅ 菜单权限清理完成</info>');
    }

    /**
     * 清理字典数据
     */
    protected function cleanupDictData(): void
    {
        $this->output->section('📚 清理字典数据');
        
        // 1. 删除字段绑定关系
        $this->cleanupFieldBindings();
        
        // 2. 删除字典数据
        $this->cleanupDictValues();
        
        // 3. 删除字典类型
        $this->cleanupDictTypes();

        // 4. 清理系统模块OAuth字典数据
        $this->cleanupSystemModuleDictData();
        
        $this->output->writeln('<info>✅ 字典数据清理完成</info>');
    }

    protected function cleanupSystemModuleDictData(): void
    {
        $dictData = SysDictData::where('dict_type', 'system_module')
            ->where('dict_value', 'oauthConfig')
            ->first();

        if ($dictData) {
            $dictData->delete();
            $this->output->writeln("  - 删除字典数据: {$dictData->dict_name} ({$dictData->dict_value})");
        }
    }

    /**
     * 清理字段绑定关系
     */
    protected function cleanupFieldBindings(): void
    {
        $bindings = SysFieldDict::where('table_name', 'sys_oauth_config')->get();
        
        foreach ($bindings as $binding) {
            $binding->delete();
            $this->output->writeln("  - 删除字段绑定: {$binding->table_name}.{$binding->field_name} → {$binding->dict_type}");
        }
    }

    /**
     * 清理字典数据
     */
    protected function cleanupDictValues(): void
    {
        foreach ($this->dictTypes as $dictType) {
            $count = SysDictData::where('dict_type', $dictType)->count();
            if ($count > 0) {
                SysDictData::where('dict_type', $dictType)->delete();
                $this->output->writeln("  - 删除字典数据: {$dictType} ({$count} 条)");
            }
        }
    }

    /**
     * 清理字典类型
     */
    protected function cleanupDictTypes(): void
    {
        foreach ($this->dictTypes as $dictType) {
            $dictTypeModel = SysDictType::where('dict_type', $dictType)->first();
            if ($dictTypeModel) {
                $dictTypeModel->delete();
                $this->output->writeln("  - 删除字典类型: {$dictTypeModel->dict_name} ({$dictType})");
            }
        }
    }

    /**
     * 备份前端文件
     */
    protected function backupFrontendFiles(string $backupDir): void
    {
        $adminWebDir = $this->container->get(ConfigInterface::class)->get('hyperf-common.admin_web');
        
        if (empty($adminWebDir) || !is_dir($adminWebDir)) {
            $this->output->writeln("  - 前端项目路径未配置或不存在，跳过前端文件备份");
            return;
        }
        
        $frontendFiles = [
            'api' => $adminWebDir . '/src/api/system/oauth.js',
            'view' => $adminWebDir . '/src/views/system/oauth/index.vue'
        ];
        
        $backupFiles = [];
        foreach ($frontendFiles as $type => $filePath) {
            if (file_exists($filePath)) {
                $content = file_get_contents($filePath);
                $backupFiles[$type] = [
                    'path' => $filePath,
                    'content' => $content,
                    'size' => strlen($content)
                ];
            }
        }
        
        if (!empty($backupFiles)) {
            $backupFile = $backupDir . '/frontend_files.json';
            file_put_contents($backupFile, json_encode($backupFiles, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            $this->output->writeln("  - 备份前端文件: " . count($backupFiles) . " 个文件");
        }
    }

    /**
     * 清理前端文件
     */
    protected function cleanupFrontendFiles(): void
    {
        $this->output->section('🗂️ 清理前端文件');
        
        $adminWebDir = $this->container->get(ConfigInterface::class)->get('hyperf-common.admin_web');
        
        if (empty($adminWebDir)) {
            $this->output->writeln('<comment>未配置前端项目路径(ADMIN_WEB_DIR_PATH)，跳过前端文件清理</comment>');
            return;
        }
        
        if (!is_dir($adminWebDir)) {
            $this->output->writeln("<comment>前端项目目录不存在: {$adminWebDir}</comment>");
            return;
        }
        
        $this->output->writeln("  前端项目路径: {$adminWebDir}");

        $baseDir = $adminWebDir . '/src';

        $relativeApiPath = '/api/system/oauth.js';
        $relativeViewPath = '/views/system/oauth/index.vue';
        $relativeViewDir = '/views/system/oauth';

        // 定义要清理的文件和目录
        $filesToRemove = [
            'API文件' => $baseDir . $relativeApiPath,
            '视图文件' => $baseDir . $relativeViewPath
        ];
        
        $dirsToRemove = [
            'OAuth视图目录' => $baseDir . $relativeViewDir
        ];
        
        // 删除文件
        foreach ($filesToRemove as $name => $filePath) {
            if (file_exists($filePath)) {
                if (unlink($filePath)) {
                    $this->output->writeln("  ✓ 删除{$name}: " . basename($filePath));
                } else {
                    $this->output->writeln("  ✗ 删除{$name}失败: " . basename($filePath));
                }
            } else {
                $this->output->writeln("  - {$name}不存在，跳过删除");
            }
        }
        
        // 删除目录（仅当目录为空时）
        foreach ($dirsToRemove as $name => $dirPath) {
            if (is_dir($dirPath)) {
                // 检查目录是否为空
                $files = array_diff(scandir($dirPath), array('.', '..'));
                if (empty($files)) {
                    if (rmdir($dirPath)) {
                        $this->output->writeln("  ✓ 删除{$name}: " . basename($dirPath));
                    } else {
                        $this->output->writeln("  ✗ 删除{$name}失败: " . basename($dirPath));
                    }
                } else {
                    $this->output->writeln("  - {$name}非空，跳过删除: " . basename($dirPath));
                }
            } else {
                $this->output->writeln("  - {$name}不存在，跳过删除");
            }
        }
        
        $this->output->writeln('<info>✅ 前端文件清理完成</info>');
    }

    /**
     * 删除数据库表
     */
    protected function dropDatabaseTables(): void
    {
        $this->output->section('🗄️ 删除数据库表');
        
        // 检查存在的表
        $existingTables = [];
        foreach ($this->tables as $table) {
            if (Schema::hasTable($table)) {
                $existingTables[] = $table;
            }
        }
        
        if (empty($existingTables)) {
            $this->output->writeln('<comment>未找到需要删除的OAuth表</comment>');
            return;
        }
        
        $this->output->writeln('发现以下OAuth相关表: ' . implode(', ', $existingTables));
        
        // 禁用外键检查
        Db::statement('SET FOREIGN_KEY_CHECKS=0');
        
        try {
            foreach ($this->tables as $table) {
                if (Schema::hasTable($table)) {
                    // 获取表中数据量
                    $count = Db::table($table)->count();
                    
                    // 删除表
                    Schema::dropIfExists($table);
                    $this->output->writeln("  - 删除表: {$table} ({$count} 条记录)");
                }
            }
        } finally {
            // 重新启用外键检查
            Db::statement('SET FOREIGN_KEY_CHECKS=1');
        }
        
        $this->output->writeln('<info>✅ 数据库表删除完成</info>');
    }

    protected function cleanupPermissionData(): void
    {
        $this->output->section('📝 清理权限数据');
        
        // 删除OAuth相关API
        $count = SysApi::where('api_path', 'like', '/system/oauthConfig/%')->delete();
        $this->output->writeln("  - 删除OAuth相关API: {$count} 条");

        // 删除OAuth相关菜单
        $oauthMenus = SysMenu::where('perms', 'like', 'system:oauthConfig:%')->get();
        $allIds = $oauthMenus->pluck('menu_id')->toArray();
        $count = SysMenu::whereIn('menu_id', $allIds)->delete();
        $this->output->writeln("  - 删除OAuth相关菜单: {$count} 条");

        // 删除OAuth相关角色菜单关联
        $count = SysRoleMenu::whereIn('menu_id', $allIds)->delete();
        $this->output->writeln("  - 删除OAuth相关角色菜单关联: {$count} 条");
        
        // 删除菜单api绑定数据
        $count = SysMenuApi::whereIn('menu_id', $allIds)->delete();
        $this->output->writeln("  - 删除OAuth相关菜单api绑定数据: {$count} 条");

        $this->output->writeln('<info>✅ 权限数据清理完成</info>');
    }

    /**
     * 打印卸载总结
     */
    protected function printUninstallSummary(): void
    {
        $this->output->section('📊 卸载总结');
        
        $this->output->writeln('已清理的组件:');
        
        if (!$this->input->getOption('skip-tables')) {
            $this->output->writeln('数据库表:');
            foreach ($this->tables as $table) {
                $this->output->writeln("  ✓ {$table}");
            }
        }
        
        if (!$this->input->getOption('skip-dict')) {
            $this->output->writeln('字典数据:');
            foreach ($this->dictTypes as $dictType) {
                $this->output->writeln("  ✓ {$dictType}");
            }
            $this->output->writeln('  ✓ 字段绑定关系');
        }
        
        if (!$this->input->getOption('skip-menu')) {
            $this->output->writeln('菜单权限:');
            $this->output->writeln('  ✓ 平台配置页面菜单');
            $this->output->writeln('  ✓ 平台配置相关按钮权限');
            $this->output->writeln('  ✓ 相关角色权限分配');
        }
        
        if (!$this->input->getOption('skip-frontend')) {
            $this->output->writeln('前端文件:');
            $this->output->writeln('  ✓ OAuth API文件');
            $this->output->writeln('  ✓ OAuth视图文件');
            $this->output->writeln('  ✓ OAuth目录');
        }
        
        if ($this->input->getOption('backup')) {
            $this->output->writeln('');
            $this->output->writeln('<info>💾 数据已备份，如需恢复可查看备份文件</info>');
        }
        
        $this->output->writeln('');
        $this->output->writeln('清理完成后的状态:');
        $this->output->writeln('  • OAuth模块的所有数据库表已删除');
        $this->output->writeln('  • OAuth相关字典配置已清理');
        $this->output->writeln('  • OAuth相关菜单权限已移除');
        if (!$this->input->getOption('skip-frontend')) {
            $this->output->writeln('  • OAuth前端文件已删除');
        }
        $this->output->writeln('  • 系统恢复到安装OAuth模块前的状态');
        
        $this->output->writeln('');
        $this->output->writeln('<info>🎉 OAuth模块已完全卸载！</info>');
        
        $this->output->writeln('');
        $this->output->writeln('<comment>注意事项:</comment>');
        $this->output->writeln('<comment>  • 如需重新安装，请运行: php bin/hyperf.php motong:oauth:install</comment>');
        $this->output->writeln('<comment>  • 卸载不会影响其他系统模块的功能</comment>');
        if (!$this->input->getOption('backup')) {
            $this->output->writeln('<comment>  • 未备份数据，重新安装后需要重新配置</comment>');
        }
    }
}
