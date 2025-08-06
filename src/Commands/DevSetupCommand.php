<?php

declare(strict_types=1);

namespace Motong\OAuth\Commands;

use Hyperf\Command\Command as HyperfCommand;
use Hyperf\Command\Annotation\Command;
use Hyperf\Contract\ConfigInterface;
use Psr\Container\ContainerInterface;
use Symfony\Component\Console\Input\InputOption;
use Exception;

/**
 * OAuth模块开发环境设置命令
 * 
 * 在开发环境中使用文件复制和监听实现实时同步开发
 * 支持前端Web项目和Uniapp项目的模板文件自动同步
 * 
 * @Command
 * @author Motong OAuth Team
 * @version 1.0.0
 */
class DevSetupCommand extends HyperfCommand
{
    /**
     * @var ContainerInterface
     */
    protected $container;



    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
        parent::__construct('motong:oauth:dev-setup');
    }

    public function configure()
    {
        parent::configure();
        $this->setDescription('Setup development environment with file copy and watch for OAuth module assets');
        $this->addOption('clean', 'c', InputOption::VALUE_NONE, '清理现有的文件');
        $this->addOption('force', 'f', InputOption::VALUE_NONE, '强制覆盖现有文件');
        $this->addOption('watch', 'w', InputOption::VALUE_NONE, '启动文件监听，自动同步变化');
        $this->addOption('skip-frontend', null, InputOption::VALUE_NONE, '跳过前端模板处理');
        $this->addOption('skip-uniapp', null, InputOption::VALUE_NONE, '跳过Uniapp模板处理');
    }

    public function handle()
    {
        $this->output->title('🔧 OAuth Module Development Setup');

        try {
            // 清理模式
            if ($this->input->getOption('clean')) {
                $this->cleanFiles();
                $this->output->success('✅ 清理完成！');
                return 0;
            }

            // 监听模式
            if ($this->input->getOption('watch')) {
                return $this->startFileWatcher();
            }

            // 设置前端文件
            if (!$this->input->getOption('skip-frontend')) {
                $this->setupFrontendCopy();
            } else {
                $this->output->writeln('<comment>跳过前端模板处理</comment>');
            }

            // 设置Uniapp文件
            if (!$this->input->getOption('skip-uniapp')) {
                $this->setupUniappCopy();
            } else {
                $this->output->writeln('<comment>跳过Uniapp模板处理</comment>');
            }

            $this->output->success('✅ 开发环境设置完成！');
            $this->printUsageInstructions();

        } catch (Exception $e) {
            $this->output->error('❌ 开发环境设置失败: ' . $e->getMessage());
            $this->output->writeln('<error>错误详情: ' . $e->getTraceAsString() . '</error>');
            return 1;
        }

        return 0;
    }



    /**
     * 设置前端文件复制
     */
    protected function setupFrontendCopy(): void
    {
        $this->output->section('🌐 复制前端模板文件');

        $adminWebDir = $this->container->get(ConfigInterface::class)->get('hyperf-common.admin_web');
        
        if (empty($adminWebDir)) {
            $this->output->writeln('<comment>⚠️  未配置前端项目路径(ADMIN_WEB_DIR_PATH)，跳过前端模板复制</comment>');
            return;
        }

        if (!is_dir($adminWebDir)) {
            $this->output->writeln("<comment>⚠️  前端项目目录不存在: {$adminWebDir}</comment>");
            return;
        }

        $baseSourceDir = __DIR__ . '/../assets/admin';
        $baseTargetDir = $adminWebDir . '/src';

        // 定义需要复制的文件
        $files = [
            '/api/system/oauth.js',
            '/views/system/oauth/index.vue'
        ];

        foreach ($files as $file) {
            $sourceFile = $baseSourceDir . $file;
            $targetFile = $baseTargetDir . $file;

            if (!file_exists($sourceFile)) {
                $this->output->writeln("<comment>  源文件不存在，跳过: {$file}</comment>");
                continue;
            }

            $this->copyFile($sourceFile, $targetFile, '前端');
        }
    }



    /**
     * 设置Uniapp文件复制
     */
    protected function setupUniappCopy(): void
    {
        $this->output->section('📱 复制Uniapp模板文件');

        $uniappDir = $this->container->get(ConfigInterface::class)->get('hyperf-common.uniapp_path');
        
        if (empty($uniappDir)) {
            $this->output->writeln('<comment>⚠️  未配置Uniapp项目路径(UNIAPP_PATH)，跳过Uniapp模板复制</comment>');
            return;
        }

        if (!is_dir($uniappDir)) {
            $this->output->writeln("<comment>⚠️  Uniapp项目目录不存在: {$uniappDir}</comment>");
            return;
        }

        $sourceDir = __DIR__ . '/../assets/uniapp/api/mt_oauth';
        $targetDir = $uniappDir . '/api/mt_oauth';

        if (!is_dir($sourceDir)) {
            $this->output->writeln('<comment>  源目录不存在，跳过Uniapp模板复制</comment>');
            return;
        }

        $this->copyDirectory($sourceDir, $targetDir, 'Uniapp');
    }





    /**
     * 复制文件
     */
    protected function copyFile(string $source, string $target, string $type): void
    {
        $relativePath = str_replace(dirname(dirname($target)), '', $target);
        
        // 检查目标是否已存在
        if (file_exists($target)) {
            if (!$this->input->getOption('force')) {
                $this->output->writeln("  ⚠️  {$type}目标文件已存在: {$relativePath}");
                
                if (!$this->output->confirm('是否覆盖？', false)) {
                    $this->output->writeln("  - 跳过: {$relativePath}");
                    return;
                }
            }
        }

        // 确保目标目录存在
        $targetDir = dirname($target);
        if (!is_dir($targetDir)) {
            mkdir($targetDir, 0755, true);
        }

        // 复制文件
        if (copy($source, $target)) {
            $this->output->writeln("  ✓ 复制{$type}文件: {$relativePath}");
        } else {
            $this->output->writeln("  ✗ 复制{$type}文件失败: {$relativePath}");
        }
    }

    /**
     * 复制目录
     */
    protected function copyDirectory(string $source, string $target, string $type): void
    {
        if (!is_dir($source)) {
            return;
        }

        // 检查目标目录是否已存在
        if (is_dir($target)) {
            if (!$this->input->getOption('force')) {
                $this->output->writeln("  ⚠️  {$type}目标目录已存在: " . basename($target));
                
                if (!$this->output->confirm('是否覆盖？', false)) {
                    $this->output->writeln("  - 跳过目录复制");
                    return;
                }
            }
            
            // 删除现有目录
            $this->removeDirectory($target);
        }

        // 确保父目录存在
        $targetParent = dirname($target);
        if (!is_dir($targetParent)) {
            mkdir($targetParent, 0755, true);
        }

        // 递归复制目录
        $this->recursiveCopyImproved($source, $target, $type);
    }

    /**
     * 递归复制目录（改进版）
     */
    private function recursiveCopyImproved($sourceDir, $targetDir, $type)
    {
        // 确保目标目录存在
        if (!file_exists($targetDir)) {
            mkdir($targetDir, 0755, true);
        }
        
        $dir = opendir($sourceDir);
        while (($file = readdir($dir)) !== false) {
            if ($file == '.' || $file == '..') continue;
            
            $sourcePath = $sourceDir . '/' . $file;
            $targetPath = $targetDir . '/' . $file;

            if (is_dir($sourcePath)) {
                $this->recursiveCopyImproved($sourcePath, $targetPath, $type);
            } else {
                if (copy($sourcePath, $targetPath)) {
                    $this->output->writeln("  ✓ 复制{$type}文件: {$file}");
                } else {
                    $this->output->writeln("  ✗ 复制{$type}文件失败: {$file}");
                }
            }
        }
        closedir($dir);
    }

    /**
     * 启动文件监听
     */
    protected function startFileWatcher(): int
    {
        $this->output->section('👀 启动文件监听服务');
        
        // 检查是否安装了必要的扩展
        if (!extension_loaded('inotify')) {
            $this->output->writeln('<comment>⚠️  未安装inotify扩展，使用轮询模式监听文件变化</comment>');
            $this->output->writeln('<comment>提示：安装inotify扩展可获得更好的性能</comment>');
        }

        $this->output->writeln('<info>🚀 文件监听服务已启动</info>');
        $this->output->writeln('<comment>监听以下目录的文件变化：</comment>');
        
        $watchDirs = [];
        
        // 添加前端资源监听
        $frontendSourceDir = __DIR__ . '/../assets/admin';
        if (is_dir($frontendSourceDir)) {
            $watchDirs['frontend'] = $frontendSourceDir;
            $this->output->writeln("  - 前端模板: {$frontendSourceDir}");
        }

        // 添加Uniapp资源监听
        $uniappSourceDir = __DIR__ . '/../assets/uniapp';
        if (is_dir($uniappSourceDir)) {
            $watchDirs['uniapp'] = $uniappSourceDir;
            $this->output->writeln("  - Uniapp模板: {$uniappSourceDir}");
        }

        if (empty($watchDirs)) {
            $this->output->error('❌ 没有找到需要监听的目录');
            return 1;
        }

        $this->output->writeln('');
        $this->output->writeln('<info>按 Ctrl+C 停止监听服务</info>');
        $this->output->writeln('');

        // 记录文件的最后修改时间
        $fileModTimes = [];
        
        // 初始化文件修改时间记录
        foreach ($watchDirs as $type => $dir) {
            $fileModTimes[$type] = $this->getFileModTimes($dir);
        }

        // 开始监听循环
        while (true) {
            foreach ($watchDirs as $type => $dir) {
                $currentModTimes = $this->getFileModTimes($dir);
                
                // 检查是否有文件变化
                $changes = $this->detectChanges($fileModTimes[$type], $currentModTimes);
                
                if (!empty($changes)) {
                    $this->handleFileChanges($changes, $type);
                    $fileModTimes[$type] = $currentModTimes;
                }
            }
            
            // 等待1秒后继续检查
            sleep(1);
        }
    }

    /**
     * 获取目录下所有文件的修改时间
     */
    private function getFileModTimes(string $dir): array
    {
        $modTimes = [];
        
        if (!is_dir($dir)) {
            return $modTimes;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \RecursiveDirectoryIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if ($file->isFile()) {
                $modTimes[$file->getPathname()] = $file->getMTime();
            }
        }

        return $modTimes;
    }

    /**
     * 检测文件变化
     */
    private function detectChanges(array $oldModTimes, array $newModTimes): array
    {
        $changes = [];

        // 检查新增和修改的文件
        foreach ($newModTimes as $file => $mtime) {
            if (!isset($oldModTimes[$file]) || $oldModTimes[$file] !== $mtime) {
                $changes[] = [
                    'type' => isset($oldModTimes[$file]) ? 'modified' : 'created',
                    'file' => $file
                ];
            }
        }

        // 检查删除的文件
        foreach ($oldModTimes as $file => $mtime) {
            if (!isset($newModTimes[$file])) {
                $changes[] = [
                    'type' => 'deleted',
                    'file' => $file
                ];
            }
        }

        return $changes;
    }

    /**
     * 处理文件变化
     */
    private function handleFileChanges(array $changes, string $type): void
    {
        foreach ($changes as $change) {
            $file = $change['file'];
            $changeType = $change['type'];
            $fileName = basename($file);
            
            $this->output->writeln("[" . date('H:i:s') . "] <info>{$changeType}</info> {$type}: {$fileName}");
            
            if ($changeType === 'deleted') {
                // 处理文件删除
                $this->handleFileDelete($file, $type);
            } else {
                // 处理文件创建或修改
                $this->handleFileUpdate($file, $type);
            }
        }
    }

    /**
     * 处理文件更新
     */
    private function handleFileUpdate(string $sourceFile, string $type): void
    {
        if ($type === 'frontend') {
            $this->syncFrontendFile($sourceFile);
        } elseif ($type === 'uniapp') {
            $this->syncUniappFile($sourceFile);
        }
    }

    /**
     * 处理文件删除
     */
    private function handleFileDelete(string $sourceFile, string $type): void
    {
        // 计算目标文件路径并删除
        if ($type === 'frontend') {
            $targetFile = $this->getFrontendTargetPath($sourceFile);
            if ($targetFile && file_exists($targetFile)) {
                unlink($targetFile);
                $this->output->writeln("  ✓ 删除目标文件: " . basename($targetFile));
            }
        } elseif ($type === 'uniapp') {
            $targetFile = $this->getUniappTargetPath($sourceFile);
            if ($targetFile && file_exists($targetFile)) {
                unlink($targetFile);
                $this->output->writeln("  ✓ 删除目标文件: " . basename($targetFile));
            }
        }
    }

    /**
     * 同步前端文件
     */
    private function syncFrontendFile(string $sourceFile): void
    {
        $targetFile = $this->getFrontendTargetPath($sourceFile);
        if ($targetFile) {
            $targetDir = dirname($targetFile);
            if (!is_dir($targetDir)) {
                mkdir($targetDir, 0755, true);
            }
            
            if (copy($sourceFile, $targetFile)) {
                $this->output->writeln("  ✓ 同步到: " . basename($targetFile));
            } else {
                $this->output->writeln("  ✗ 同步失败: " . basename($targetFile));
            }
        }
    }

    /**
     * 同步Uniapp文件
     */
    private function syncUniappFile(string $sourceFile): void
    {
        $targetFile = $this->getUniappTargetPath($sourceFile);
        if ($targetFile) {
            $targetDir = dirname($targetFile);
            if (!is_dir($targetDir)) {
                mkdir($targetDir, 0755, true);
            }
            
            if (copy($sourceFile, $targetFile)) {
                $this->output->writeln("  ✓ 同步到: " . basename($targetFile));
            } else {
                $this->output->writeln("  ✗ 同步失败: " . basename($targetFile));
            }
        }
    }

    /**
     * 获取前端目标文件路径
     */
    private function getFrontendTargetPath(string $sourceFile): ?string
    {
        $adminWebDir = $this->container->get(ConfigInterface::class)->get('hyperf-common.admin_web');
        if (empty($adminWebDir) || !is_dir($adminWebDir)) {
            return null;
        }

        $baseSourceDir = __DIR__ . '/../assets/admin';
        $relativePath = str_replace($baseSourceDir, '', $sourceFile);
        
        return $adminWebDir . '/src' . $relativePath;
    }

    /**
     * 获取Uniapp目标文件路径
     */
    private function getUniappTargetPath(string $sourceFile): ?string
    {
        $uniappDir = $this->container->get(ConfigInterface::class)->get('hyperf-common.uniapp_path');
        if (empty($uniappDir) || !is_dir($uniappDir)) {
            return null;
        }

        $baseSourceDir = __DIR__ . '/../assets/uniapp';
        $relativePath = str_replace($baseSourceDir, '', $sourceFile);
        
        return $uniappDir . $relativePath;
    }

    /**
     * 清理文件
     */
    protected function cleanFiles(): void
    {
        $this->output->section('🧹 清理现有文件');

        $cleaned = 0;

        // 清理前端文件
        $adminWebDir = $this->container->get(ConfigInterface::class)->get('hyperf-common.admin_web');
        if (!empty($adminWebDir) && is_dir($adminWebDir)) {
            $frontendFiles = [
                $adminWebDir . '/src/api/system/oauth.js',
                $adminWebDir . '/src/views/system/oauth/index.vue'
            ];

            foreach ($frontendFiles as $file) {
                if (file_exists($file)) {
                    unlink($file);
                    $this->output->writeln("  ✓ 清理前端文件: " . basename($file));
                    $cleaned++;
                }
            }
        }

        // 清理Uniapp文件
        $uniappDir = $this->container->get(ConfigInterface::class)->get('hyperf-common.uniapp_path');
        if (!empty($uniappDir) && is_dir($uniappDir)) {
            $uniappDir = $uniappDir . '/api/mt_oauth';
            
            if (is_dir($uniappDir)) {
                $this->removeDirectory($uniappDir);
                $this->output->writeln("  ✓ 清理Uniapp目录: mt_oauth");
                $cleaned++;
            }
        }

        if ($cleaned === 0) {
            $this->output->writeln('  - 没有找到需要清理的文件');
        } else {
            $this->output->writeln("<info>  清理了 {$cleaned} 个文件/目录</info>");
        }
    }

    /**
     * 递归删除目录
     */
    protected function removeDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $files = array_diff(scandir($dir), ['.', '..']);
        
        foreach ($files as $file) {
            $path = $dir . '/' . $file;
            
            if (is_dir($path)) {
                $this->removeDirectory($path);
            } else {
                unlink($path);
            }
        }
        
        rmdir($dir);
    }

    /**
     * 打印使用说明
     */
    protected function printUsageInstructions(): void
    {
        $this->output->section('📖 使用说明');
        
        $this->output->writeln("开发环境已设置完成（文件复制模式），现在你可以：");
        $this->output->writeln('');
        $this->output->writeln('1. 直接在扩展目录中修改模板文件：');
        $this->output->writeln('   - extensions/MotongOAuth/src/assets/admin/');
        $this->output->writeln('   - extensions/MotongOAuth/src/assets/uniapp/');
        $this->output->writeln('');
        $this->output->writeln('2. 启动文件监听服务实现自动同步：');
        $this->output->writeln('   php bin/hyperf.php motong:oauth:dev-setup --watch');
        $this->output->writeln('');
        $this->output->writeln('3. 常用命令：');
        $this->output->writeln('   - 重新复制文件: php bin/hyperf.php motong:oauth:dev-setup --force');
        $this->output->writeln('   - 启动文件监听: php bin/hyperf.php motong:oauth:dev-setup --watch');
        $this->output->writeln('   - 清理所有文件: php bin/hyperf.php motong:oauth:dev-setup --clean');
        $this->output->writeln('');
        $this->output->writeln('<info>💡 推荐：启动文件监听服务，实现修改后自动同步</info>');
        $this->output->writeln('<info>💡 提示：生产环境部署时请使用 motong:oauth:install 命令进行正常安装</info>');
    }
}
