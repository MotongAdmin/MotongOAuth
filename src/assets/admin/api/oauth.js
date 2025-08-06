import { callService } from '@/api/common'

/**
 * OAuth配置管理相关API
 */

// 获取OAuth配置列表
export function getOAuthConfigList(params) {
  return callService('admin.oauthConfig.getList', {
    page: params.page || 1,
    page_size: params.pageSize || 10,
    platform: params.platform,
    client_type: params.clientType,
    status: params.status,
    name: params.name
  })
}

// 获取OAuth配置详情
export function getOAuthConfigDetail(id) {
  return callService('admin.oauthConfig.getDetail', { id })
}

// 创建OAuth配置
export function createOAuthConfig(data) {
  return callService('admin.oauthConfig.create', {
    name: data.name,
    description: data.description,
    platform: data.platform,
    client_type: data.clientType,
    app_id: data.appId,
    app_secret: data.appSecret,
    auth_redirect: data.authRedirect,
    scopes: data.scopes,
    message_token: data.messageToken,
    message_aeskey: data.messageAeskey,
    extra_config: data.extraConfig,
    status: data.status,
    sort_order: data.sortOrder
  })
}

// 更新OAuth配置
export function updateOAuthConfig(id, data) {
  return callService('admin.oauthConfig.update', {
    id: id,
    name: data.name,
    description: data.description,
    app_secret: data.appSecret,
    auth_redirect: data.authRedirect,
    scopes: data.scopes,
    message_token: data.messageToken,
    message_aeskey: data.messageAeskey,
    extra_config: data.extraConfig,
    status: data.status,
    sort_order: data.sortOrder
  })
}

// 删除OAuth配置
export function deleteOAuthConfig(id) {
  return callService('admin.oauthConfig.delete', { id })
}

// 启用OAuth配置
export function enableOAuthConfig(id) {
  return callService('admin.oauthConfig.enable', { id })
}

// 禁用OAuth配置
export function disableOAuthConfig(id) {
  return callService('admin.oauthConfig.disable', { id })
}

// 测试OAuth配置连接
export function testOAuthConnection(id) {
  return callService('admin.oauthConfig.testConnection', { id })
}

// 获取支持的平台和客户端类型
export function getSupportedOptions() {
  return callService('admin.oauthConfig.getSupportedOptions', {})
}

// 批量操作OAuth配置
export function batchActionOAuthConfig(ids, action) {
  return callService('admin.oauthConfig.batchAction', {
    ids: ids,
    action: action
  })
}