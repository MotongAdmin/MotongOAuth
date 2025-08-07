<?php

declare(strict_types=1);

namespace Motong\OAuth\Controller\Admin\System;

use Motong\OAuth\Service\Admin\System\OAuthConfigService;
use App\Annotation\Description;
use ZYProSoft\Controller\AbstractController;
use Hyperf\HttpServer\Annotation\AutoController;
use Hyperf\Di\Annotation\Inject;
use ZYProSoft\Http\AuthedRequest;

/**
 * @AutoController(prefix="/system/oauthConfig")
 * OAuth配置管理控制器 - 管理后台
 * 
 * @author Motong OAuth Team
 * @version 1.0.0
 */
class OAuthConfigController extends AbstractController
{
    /**
     * @Inject
     * @var OAuthConfigService
     */
    protected OAuthConfigService $service;

    /**
     * 自定义验证错误消息
     * @return array
     */
    public function messages()
    {
        return [
            // 通用规则消息
            'required' => ':attribute不能为空',
            'string' => ':attribute必须是字符串',
            'integer' => ':attribute必须是整数',
            'min' => ':attribute长度不能少于:min位',
            'max' => ':attribute长度不能超过:max位',
            'url' => ':attribute必须是有效的URL',
            'in' => ':attribute的值不在允许范围内',
            'array' => ':attribute必须是数组',
            
            // 字段特定消息
            'name.required' => '配置名称不能为空',
            'name.max' => '配置名称长度不能超过100个字符',
            'description.max' => '配置描述长度不能超过500个字符',
            'platform.required' => '平台类型不能为空',
            'platform.max' => '平台类型长度不能超过20个字符',
            'client_type.required' => '客户端类型不能为空',
            'client_type.max' => '客户端类型长度不能超过20个字符',
            'app_id.required' => 'AppID不能为空',
            'app_id.max' => 'AppID长度不能超过100个字符',
            'app_secret.required' => 'AppSecret不能为空',
            'app_secret.max' => 'AppSecret长度不能超过100个字符',
            'auth_redirect.url' => '授权回调地址必须是有效的URL',
            'auth_redirect.max' => '授权回调地址长度不能超过500个字符',
            'scopes.max' => '授权范围长度不能超过255个字符',
            'message_token.max' => '消息校验Token长度不能超过100个字符',
            'message_aeskey.max' => '消息加解密密钥长度不能超过100个字符',
            'status.in' => '状态值只能是0或1',
            'sort_order.min' => '排序权重不能小于0',
            'page.min' => '页码不能小于1',
            'page_size.min' => '每页数量不能小于1',
            'page_size.max' => '每页数量不能超过100',
            'id.required' => 'ID不能为空',
            'id.min' => 'ID不能小于1',
            'ids.required' => 'ID列表不能为空',
            'action.required' => '操作类型不能为空',
            'action.in' => '操作类型只能是enable、disable、delete中的一种',
        ];
    }

    /**
     * @Description(value="获取OAuth配置列表")
     * 获取OAuth配置列表
     * @param AuthedRequest $request
     * @return \Psr\Http\Message\ResponseInterface
     */
    public function getList(AuthedRequest $request)
    {
        $this->validate([
            'page' => 'nullable|integer|min:1',
            'page_size' => 'nullable|integer|min:1|max:100',
            'platform' => 'nullable|string',
            'client_type' => 'nullable|string',
            'status' => 'nullable|integer|in:0,1',
            'name' => 'nullable|string',
        ]);

        $page = $request->param('page', 1);
        $pageSize = $request->param('page_size', 10);
        
        // 构建过滤条件
        $filters = [];
        if (!empty($request->param('platform'))) {
            $filters['platform'] = $request->param('platform');
        }
        if (!empty($request->param('client_type'))) {
            $filters['client_type'] = $request->param('client_type');
        }
        if ($request->has('status')) {
            $filters['status'] = (int)$request->param('status');
        }
        if (!empty($request->param('name'))) {
            $filters['name'] = $request->param('name');
        }

        $result = $this->service->getList($page, $pageSize, $filters);
        
        return $this->success($result);
    }

    /**
     * @Description(value="获取OAuth配置详情")
     * 获取OAuth配置详情
     * @param AuthedRequest $request
     * @return \Psr\Http\Message\ResponseInterface
     */
    public function getDetail(AuthedRequest $request)
    {
        $this->validate([
            'id' => 'required|integer|min:1',
        ]);

        $id = (int)$request->param('id');
        $result = $this->service->getDetail($id);
        
        return $this->success($result);
    }

    /**
     * @Description(value="创建OAuth配置")
     * 创建OAuth配置
     * @param AuthedRequest $request
     * @return \Psr\Http\Message\ResponseInterface
     */
    public function create(AuthedRequest $request)
    {
        $this->validate([
            'name' => 'required|string|max:100',
            'description' => 'nullable|string|max:500',
            'platform' => 'required|string|max:20',
            'client_type' => 'required|string|max:20',
            'app_id' => 'required|string|max:100',  
            'app_secret' => 'required|string|max:100',
            'auth_redirect' => 'nullable|string|url|max:500',
            'scopes' => 'nullable|string|max:255',
            'message_token' => 'nullable|string|max:100',
            'message_aeskey' => 'nullable|string|max:100',
            'extra_config' => 'nullable|array',
            'status' => 'nullable|integer|in:0,1',
            'sort_order' => 'nullable|integer|min:0',
        ]);

        $data = [
            'name' => $request->param('name'),
            'description' => $request->param('description', ''),
            'platform' => $request->param('platform'),
            'client_type' => $request->param('client_type'),
            'app_id' => $request->param('app_id'),
            'app_secret' => $request->param('app_secret'),
            'auth_redirect' => $request->param('auth_redirect', ''),
            'scopes' => $request->param('scopes', 'snsapi_userinfo'),
            'message_token' => $request->param('message_token', ''),
            'message_aeskey' => $request->param('message_aeskey', ''),
            'extra_config' => $request->param('extra_config', []),
            'status' => $request->param('status', 1),
            'sort_order' => $request->param('sort_order', 0),
        ]; 

        $result = $this->service->create($data);
        
        return $this->success($result);
    }

    /**
     * @Description(value="更新OAuth配置")
     * 更新OAuth配置
     * @param AuthedRequest $request
     * @return \Psr\Http\Message\ResponseInterface
     */
    public function update(AuthedRequest $request)
    {
        $this->validate([
            'id' => 'required|integer|min:1',
            'name' => 'nullable|string|max:100',
            'description' => 'nullable|string|max:500',
            'app_secret' => 'nullable|string|max:100',
            'auth_redirect' => 'nullable|string|url|max:500',
            'scopes' => 'nullable|string|max:255',
            'message_token' => 'nullable|string|max:100',
            'message_aeskey' => 'nullable|string|max:100',
            'extra_config' => 'nullable|array',
            'status' => 'nullable|integer|in:0,1',
            'sort_order' => 'nullable|integer|min:0',
        ]);

        $id = (int)$request->param('id');
        $data = $request->all();
        unset($data['id']);

        $this->service->update($id, $data);
        
        return $this->success([]);
    }

    /**
     * @Description(value="删除OAuth配置")
     * 删除OAuth配置
     * @param AuthedRequest $request
     * @return \Psr\Http\Message\ResponseInterface
     */
    public function delete(AuthedRequest $request)
    {
        $this->validate([
            'id' => 'required|integer|min:1',
        ]);

        $id = (int)$request->param('id');
        $this->service->delete($id);
        
        return $this->success([]);
    }

    /**
     * @Description(value="启用OAuth配置")
     * 启用OAuth配置
     * @param AuthedRequest $request
     * @return \Psr\Http\Message\ResponseInterface
     */
    public function enable(AuthedRequest $request)
    {
        $this->validate([
            'id' => 'required|integer|min:1',
        ]);

        $id = (int)$request->param('id');
        $this->service->enable($id);
        
        return $this->success([]);
    }

    /**
     * @Description(value="禁用OAuth配置")
     * 禁用OAuth配置
     * @param AuthedRequest $request
     * @return \Psr\Http\Message\ResponseInterface
     */
    public function disable(AuthedRequest $request)
    {
        $this->validate([
            'id' => 'required|integer|min:1',
        ]);

        $id = (int)$request->param('id');
        $this->service->disable($id);
        
        return $this->success([]);
    }

    /**
     * @Description(value="测试OAuth配置连接")
     * 测试OAuth配置连接
     * @param AuthedRequest $request
     * @return \Psr\Http\Message\ResponseInterface
     */
    public function testConnection(AuthedRequest $request)
    {
        $this->validate([
            'id' => 'required|integer|min:1',
        ]);

        $id = (int)$request->param('id');
        $result = $this->service->testConnection($id);
        
        return $this->success($result);
    }

    /**
     * @Description(value="获取支持的平台和客户端类型")
     * 获取支持的平台和客户端类型
     * @param AuthedRequest $request
     * @return \Psr\Http\Message\ResponseInterface
     */
    public function getSupportedOptions(AuthedRequest $request)
    {
        $result = $this->service->getSupportedOptions();
        
        return $this->success($result);
    }

    /**
     * @Description(value="批量操作OAuth配置")
     * 批量操作OAuth配置
     * @param AuthedRequest $request
     * @return \Psr\Http\Message\ResponseInterface
     */
    public function batchAction(AuthedRequest $request)
    {
        $this->validate([
            'ids' => 'required|array',
            'action' => 'required|string|in:enable,disable,delete',
        ]);

        $ids = $request->param('ids');
        $action = $request->param('action');

        // 验证ID数组
        foreach ($ids as $id) {
            if (!is_int($id) && !ctype_digit((string)$id)) {
                throw new \ZYProSoft\Exception\HyperfCommonException(\App\Constants\ErrorCode::PARAM_ERROR, 'ID必须是整数');
            }
        }

        $result = $this->service->batchAction($ids, $action);
        
        return $this->success($result);
    }
}