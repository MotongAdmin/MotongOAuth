# OAuth模块开发环境配置指南

## 概述

OAuth模块提供了专门的开发环境配置命令，使用文件复制和监听模式实现开发时的实时同步。这大大提高了开发效率，避免了频繁的手动文件复制操作。

## 快速开始

### 基本使用

```bash
# 1. 复制文件到目标项目
php bin/hyperf.php motong:oauth:dev-setup

# 2. 启动文件监听服务（实现自动同步）
php bin/hyperf.php motong:oauth:dev-setup --watch
```

### 其他选项

```bash
# 只设置前端，跳过Uniapp
php bin/hyperf.php motong:oauth:dev-setup --skip-uniapp

# 只设置Uniapp，跳过前端
php bin/hyperf.php motong:oauth:dev-setup --skip-frontend

# 强制覆盖现有文件
php bin/hyperf.php motong:oauth:dev-setup --force

# 清理所有文件
php bin/hyperf.php motong:oauth:dev-setup --clean
```

## 环境要求

### 必需配置

在 `.env` 文件中设置以下环境变量：

```env
# 前端Web项目路径（可选）
ADMIN_WEB_DIR_PATH=/path/to/MotongAdminWeb

# Uniapp项目路径（可选）
UNIAPP_PATH=/path/to/MotongUniapp
```

## 工作原理

### 文件复制 + 监听模式

- 初始复制源文件到目标位置
- 启动文件监听服务，检测源文件变化
- 自动同步变化到目标位置
- 兼容所有前端构建工具

### 文件映射关系

#### 前端Web项目
```
源文件: extensions/MotongOAuth/src/assets/admin/api/system/oauth.js
目标: MotongAdminWeb/src/api/system/oauth.js

源文件: extensions/MotongOAuth/src/assets/admin/views/system/oauth/index.vue  
目标: MotongAdminWeb/src/views/system/oauth/index.vue
```

#### Uniapp项目
```
源目录: extensions/MotongOAuth/src/assets/uniapp/api/mt_oauth/
目标: MotongUniapp/api/mt_oauth/
```

### 开发流程

1. **初始化开发环境**
   ```bash
   # 复制文件到目标项目
   php bin/hyperf.php motong:oauth:dev-setup
   ```

2. **启动文件监听服务**
   ```bash
   # 在新终端窗口中启动监听服务
   php bin/hyperf.php motong:oauth:dev-setup --watch
   ```

3. **开始开发**
   - 修改 `extensions/MotongOAuth/src/assets/admin/` 下的前端文件
   - 修改 `extensions/MotongOAuth/src/assets/uniapp/` 下的Uniapp文件
   - 监听服务会自动同步变化到目标项目

## 常见问题

### Q: 文件监听服务如何工作？

A: 监听服务会：
1. 检测源文件目录的变化（创建、修改、删除）
2. 自动同步变化到目标项目
3. 提供实时的同步日志
4. 支持多文件同时监听

### Q: 如何检查文件是否同步成功？

A: 监听服务会显示详细的同步日志：
```
[14:30:25] modified frontend: oauth.js
  ✓ 同步到: oauth.js
```

### Q: 如何停止文件监听服务？

A: 在监听服务的终端中按 `Ctrl+C`

### Q: 文件冲突怎么办？

A: 使用强制选项覆盖：
```bash
php bin/hyperf.php motong:oauth:dev-setup --force
```

### Q: 如何重新复制所有文件？

A: 使用强制选项重新复制：
```bash
php bin/hyperf.php motong:oauth:dev-setup --force
```

## 最佳实践

### 开发阶段

1. **使用文件复制 + 监听模式**
   ```bash
   # 初始复制
   php bin/hyperf.php motong:oauth:dev-setup
   
   # 启动监听（新终端窗口）
   php bin/hyperf.php motong:oauth:dev-setup --watch
   ```

2. **直接修改扩展目录中的文件**
   - `extensions/MotongOAuth/src/assets/admin/`
   - `extensions/MotongOAuth/src/assets/uniapp/`

3. **版本控制**
   - 只提交扩展目录中的源文件
   - 不要提交目标项目中的复制文件

4. **监听服务管理**
   - 保持监听服务在后台运行
   - 开发结束时按 Ctrl+C 停止监听

### 生产部署

1. **清理开发环境**
   ```bash
   php bin/hyperf.php motong:oauth:dev-setup --clean
   ```

2. **使用正式安装**
   ```bash
   php bin/hyperf.php motong:oauth:install
   ```

3. **验证部署**
   - 确保所有文件都是实际复制的
   - 测试所有功能正常工作

## 命令参数详解

### 主要选项

| 选项 | 简写 | 描述 |
|------|------|------|
| `--watch` | `-w` | 启动文件监听，自动同步变化 |
| `--clean` | `-c` | 清理现有的文件 |
| `--force` | `-f` | 强制覆盖现有文件 |
| `--skip-frontend` | | 跳过前端模板处理 |
| `--skip-uniapp` | | 跳过Uniapp模板处理 |

### 使用示例

```bash
# 基本使用：复制文件 + 监听
php bin/hyperf.php motong:oauth:dev-setup
php bin/hyperf.php motong:oauth:dev-setup --watch

# 只设置前端
php bin/hyperf.php motong:oauth:dev-setup --skip-uniapp

# 只设置Uniapp  
php bin/hyperf.php motong:oauth:dev-setup --skip-frontend

# 强制覆盖所有
php bin/hyperf.php motong:oauth:dev-setup --force

# 清理所有文件
php bin/hyperf.php motong:oauth:dev-setup --clean
```

## 故障排除

### 权限问题
```bash
# Linux/macOS
sudo php bin/hyperf.php motong:oauth:dev-setup
```

### 路径问题
检查环境变量配置：
```bash
# 查看当前配置
php bin/hyperf.php config:get hyperf-common.admin_web
php bin/hyperf.php config:get hyperf-common.uniapp_path
```

### 文件同步问题
重新复制文件：
```bash
php bin/hyperf.php motong:oauth:dev-setup --clean
php bin/hyperf.php motong:oauth:dev-setup --force
```

## 注意事项

1. **版本控制注意**：不要将复制的文件提交到版本控制系统
2. **备份重要文件**：使用 `--force` 选项前请备份重要的自定义修改
3. **监听服务管理**：开发结束后记得停止监听服务

## 技术实现

### 文件复制
```php
// 复制单个文件
copy($sourceFile, $targetFile);

// 递归复制目录
$this->recursiveCopyImproved($sourceDir, $targetDir, $type);
```

### 文件监听
```php
// 获取文件修改时间
$modTimes[$file->getPathname()] = $file->getMTime();

// 检测文件变化
$changes = $this->detectChanges($oldModTimes, $newModTimes);
```

## 总结

OAuth模块开发环境配置使用文件复制 + 监听模式：

- ✅ 兼容所有前端构建工具
- ✅ 实时自动同步
- ✅ 详细的同步日志
- ✅ 简单易用

**这种模式既保证了兼容性，又实现了开发效率的提升！**