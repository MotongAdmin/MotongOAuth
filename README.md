# Motong OAuth 第三方登录模块

基于 Hyperf 框架的通用第三方登录模块，支持多种OAuth平台的接入。

## 功能特性

- 🚀 **通用设计**: 支持多种第三方OAuth平台，易于扩展
- 🔐 **安全可靠**: 完整的OAuth2.0流程实现，支持state防CSRF攻击
- 🎯 **多端支持**: 同时支持Web端、小程序端、App端
- 📊 **完整日志**: 详细的登录日志记录和统计分析
- ⚙️ **灵活配置**: 支持多环境、多租户的配置管理
- 🔄 **用户绑定**: 支持用户绑定/解绑多个第三方账号

## 支持的平台

### 第一期已实现
- ✅ **微信小程序登录** - 完整实现

### 计划支持
- 📋 微信公众号网页授权
- 📋 微信开放平台App登录
- 📋 支付宝网页/小程序/App登录
- 📋 QQ互联登录
- 📋 新浪微博登录
- 📋 钉钉企业登录
- 📋 GitHub/GitLab/Gitee开发者登录

## 目录结构

```
oauth/
├── assets/
│   └── database.sql                     # 数据库表结构
├── Constants/
│   ├── OAuthPlatformConstants.php       # 平台类型常量
│   └── OAuthClientTypeConstants.php     # 客户端类型常量
├── Model/
│   ├── SysOAuthConfig.php               # OAuth配置模型
│   ├── UserOAuthBind.php                # 用户绑定模型
│   ├── OAuthAuthState.php               # 授权状态模型
│   └── OAuthLoginLog.php                # 登录日志模型
├── Service/
│   ├── Provider/
│   │   ├── AbstractOAuthProvider.php    # OAuth提供者抽象基类
│   │   └── WeChatMiniappProvider.php    # 微信小程序提供者
│   ├── Admin/
│   │   └── System/
│   │       └── OAuthConfigService.php   # 配置管理服务（管理后台）
│   ├── OAuthConfigService.php           # 配置基础服务（认证用）
│   ├── OAuthAuthService.php             # 认证服务
│   └── UserOAuthBindService.php         # 绑定服务
├── Controller/
│   ├── Common/
│   │   └── OAuthController.php          # OAuth认证控制器（公共）
│   └── Admin/
│       └── System/
│           └── OAuthConfigController.php # OAuth配置管理控制器（管理后台）
└── README.md                            # 说明文档
```

## 安装和配置

### 1. 导入数据库表结构

```bash
# 执行 assets/database.sql 中的SQL语句
mysql -u username -p database_name < assets/database.sql
```

### 2. 配置OAuth平台信息

通过管理后台或直接插入数据库添加OAuth配置：

```sql
INSERT INTO sys_oauth_config (name, platform, client_type, app_id, app_secret, status) 
VALUES ('微信小程序', 'wechat_miniapp', 'miniapp', 'your_miniapp_appid', 'encrypted_secret', 1);
```

### 3. 集成到项目中

OAuth模块已采用注解路由方式，控制器会自动注册路由：

**公共接口路由**（自动注册）：
- `/common/oauth/*` - 用户OAuth认证相关接口

**管理后台路由**（自动注册）：
- `/admin/oauth-config/*` - OAuth配置管理接口

**ZGW接口名映射**：
- `common.oauth.loginByCode` → `OAuthController::loginByCode()`
- `admin.oauthConfig.getList` → `OAuthConfigController::getList()`
- 更多接口详见API文档部分

## 使用示例

### 微信小程序登录

#### 1. 小程序端获取登录码

```javascript
// 小程序端
wx.login({
  success: (res) => {
    if (res.code) {
      // 调用后端登录接口
      this.loginWithCode(res.code);
    }
  }
});
```

#### 2. 调用后端登录接口

```javascript
// 调用OAuth登录接口
const response = await fetch('/api/oauth/loginByCode', {
  method: 'POST',
  headers: { 'Content-Type': 'application/json' },
  body: JSON.stringify({
    platform: 'wechat_miniapp',
    code: loginCode,
    state: '' // 小程序登录可选
  })
});

const result = await response.json();
if (result.code === 200) {
  // 登录成功，保存token
  wx.setStorageSync('token', result.data.token);
  // 跳转到主页
  wx.switchTab({ url: '/pages/index/index' });
}
```

### Web端第三方登录

#### 1. 获取授权URL

```javascript
// 获取授权URL
const response = await fetch('/api/oauth/getAuthUrl', {
  method: 'POST',
  headers: { 'Content-Type': 'application/json' },
  body: JSON.stringify({
    platform: 'wechat_mp',
    client_type: 'web',
    action: 'login',
    redirect_url: 'https://yourdomain.com/dashboard'
  })
});

const result = await response.json();
if (result.code === 200) {
  // 跳转到第三方授权页面
  window.location.href = result.data.auth_url;
}
```

#### 2. 处理授权回调

```javascript
// 在回调页面处理授权结果
const urlParams = new URLSearchParams(window.location.search);
const code = urlParams.get('code');
const state = urlParams.get('state');

if (code && state) {
  // 调用登录接口
  const response = await fetch('/api/oauth/loginByCode', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({
      platform: 'wechat_mp',
      code: code,
      state: state
    })
  });
  
  const result = await response.json();
  if (result.code === 200) {
    // 登录成功
    localStorage.setItem('token', result.data.token);
    window.location.href = '/dashboard';
  }
}
```

### 绑定第三方账号

```javascript
// 已登录用户绑定第三方账号
const response = await fetch('/api/oauth/bindAccount', {
  method: 'POST',
  headers: { 
    'Content-Type': 'application/json',
    'Authorization': 'Bearer ' + token
  },
  body: JSON.stringify({
    platform: 'wechat_mp',
    code: authCode,
    state: stateParam
  })
});

const result = await response.json();
if (result.code === 200) {
  alert('绑定成功');
}
```

## API接口说明

### 公共接口（遵循ZGW协议）

| ZGW接口名 | 说明 | HTTP路径 | Controller方法 |
|-----------|------|----------|----------------|
| common.oauth.getAuthUrl | 获取OAuth授权URL | /common/oauth/getAuthUrl | getAuthUrl() |
| common.oauth.loginByCode | OAuth登录 | /common/oauth/loginByCode | loginByCode() |
| common.oauth.bindAccount | 绑定第三方账号⚡ | /common/oauth/bindAccount | bindAccount() |
| common.oauth.unbindAccount | 解绑第三方账号⚡ | /common/oauth/unbindAccount | unbindAccount() |
| common.oauth.getBindList | 获取绑定列表⚡ | /common/oauth/getBindList | getBindList() |
| common.oauth.refreshToken | 刷新访问令牌⚡ | /common/oauth/refreshToken | refreshToken() |
| common.oauth.wechatMiniappLogin | 微信小程序专用登录 | /common/oauth/wechatMiniappLogin | wechatMiniappLogin() |
| common.oauth.getSupportedPlatforms | 获取支持的平台 | /common/oauth/getSupportedPlatforms | getSupportedPlatforms() |

> ⚡ 标记的接口需要用户登录（JWT认证）

### 管理后台接口（遵循ZGW协议）

| ZGW接口名 | 说明 | HTTP路径 | Controller方法 |
|-----------|------|----------|----------------|
| admin.oauthConfig.getList | 获取配置列表 | /admin/oauth-config/getList | getList() |
| admin.oauthConfig.getDetail | 获取配置详情 | /admin/oauth-config/getDetail | getDetail() |
| admin.oauthConfig.create | 创建配置 | /admin/oauth-config/create | create() |
| admin.oauthConfig.update | 更新配置 | /admin/oauth-config/update | update() |
| admin.oauthConfig.delete | 删除配置 | /admin/oauth-config/delete | delete() |
| admin.oauthConfig.enable | 启用配置 | /admin/oauth-config/enable | enable() |
| admin.oauthConfig.disable | 禁用配置 | /admin/oauth-config/disable | disable() |
| admin.oauthConfig.testConnection | 测试连接 | /admin/oauth-config/testConnection | testConnection() |
| admin.oauthConfig.getSupportedOptions | 获取支持选项 | /admin/oauth-config/getSupportedOptions | getSupportedOptions() |
| admin.oauthConfig.batchAction | 批量操作 | /admin/oauth-config/batchAction | batchAction() |

## 数据库表说明

### sys_oauth_config - OAuth配置表
存储各个第三方平台的OAuth配置信息。

### user_oauth_bind - 用户绑定表
存储用户与第三方账号的绑定关系。

### oauth_auth_state - 授权状态表
存储OAuth授权过程中的state参数，防止CSRF攻击。

### oauth_login_log - 登录日志表
记录所有OAuth相关操作的详细日志。

## 扩展新平台

### 1. 创建新的Provider

```php
<?php

namespace Motong\OAuth\Service\Provider;

class CustomPlatformProvider extends AbstractOAuthProvider
{
    public function getAuthUrl(string $state, array $options = []): string
    {
        // 实现获取授权URL的逻辑
    }

    public function getUserByCode(string $code): array
    {
        // 实现通过code获取用户信息的逻辑
    }

    public function refreshToken(string $refreshToken): array
    {
        // 实现刷新令牌的逻辑
    }
}
```

### 2. 注册Provider

```php
// 在 OAuthAuthService 中注册新的Provider
private array $providers = [
    OAuthPlatformConstants::WECHAT_MINIAPP => WeChatMiniappProvider::class,
    OAuthPlatformConstants::CUSTOM_PLATFORM => CustomPlatformProvider::class,
];
```

### 3. 添加平台常量

```php
// 在 OAuthPlatformConstants 中添加新平台
const CUSTOM_PLATFORM = 'custom_platform';
```

## 安全注意事项

1. **敏感信息加密**: AppSecret等敏感配置必须加密存储
2. **State参数验证**: 严格验证state参数防止CSRF攻击
3. **Token安全**: 访问令牌应加密存储并设置合理的过期时间
4. **HTTPS传输**: 生产环境必须使用HTTPS协议
5. **权限控制**: 配置管理接口需要适当的权限控制

## 常见问题

### Q: 微信小程序登录失败？
A: 检查AppID和AppSecret是否正确，确保小程序已发布或在开发者工具中测试。

### Q: 如何处理UnionID？
A: 对于微信生态，可以通过UnionID关联同一用户在不同应用中的身份。

### Q: 如何自定义用户创建逻辑？
A: 修改 `OAuthAuthService::createUserFromOAuth()` 方法，集成你的用户管理系统。

## 版本历史

- v1.0.0 - 初始版本，支持微信小程序登录
- 计划v1.1.0 - 支持微信公众号网页授权
- 计划v1.2.0 - 支持支付宝登录

## 架构特点

### 🏗️ 模块化设计
- **Controller层**：按功能分离，Common用于公共接口，Admin用于管理后台
- **Service层**：分层设计，基础服务用于认证，Admin服务用于管理并继承BaseService
- **Model层**：统一数据模型，支持缓存和作用域查询

### 📋 规范遵循
- **ZGW协议**：所有接口严格遵循ZGW协议规范
- **注解路由**：使用`@AutoController`和`@Description`注解
- **操作日志**：Admin服务自动记录操作日志
- **参数验证**：统一的参数验证和错误消息

### 🔐 安全设计
- **敏感信息加密**：AppSecret等配置加密存储
- **State防护**：完整的CSRF防护机制
- **权限控制**：管理接口需要适当的权限验证
- **日志审计**：完整的操作和登录日志记录

### 🚀 扩展性
- **Provider模式**：易于扩展新的OAuth平台
- **配置化管理**：通过管理后台灵活配置
- **多端支持**：同时支持Web、小程序、App端
- **缓存优化**：关键数据支持缓存加速

## 许可证

MIT License

## 贡献

欢迎提交 Issue 和 Pull Request！