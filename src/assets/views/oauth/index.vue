<template>
  <div class="app-container">
    <!-- 搜索栏 -->
    <div class="search-box">
      <el-form :inline="true" :model="queryParams">
        <el-form-item label="配置名称">
          <el-input v-model="queryParams.name" placeholder="请输入配置名称" clearable size="small" style="width: 200px"
            @keyup.enter.native="handleQuery" />
        </el-form-item>
        <el-form-item label="平台类型">
          <dict-select 
            v-model="queryParams.platform" 
            table-name="sys_oauth_config" 
            field-name="platform"
            placeholder="请选择平台类型" 
            clearable 
            style="width: 150px" />
        </el-form-item>
        <el-form-item label="客户端类型">
          <dict-select 
            v-model="queryParams.clientType" 
            table-name="sys_oauth_config" 
            field-name="client_type"
            placeholder="请选择客户端类型" 
            clearable 
            style="width: 150px" />
        </el-form-item>
        <el-form-item label="状态">
          <dict-select 
            v-model="queryParams.status" 
            table-name="sys_oauth_config" 
            field-name="status"
            placeholder="请选择状态" 
            clearable 
            style="width: 120px" />
        </el-form-item>
        <el-form-item>
          <el-button type="primary" icon="el-icon-search" size="mini" @click="handleQuery">搜索</el-button>
          <el-button icon="el-icon-refresh" size="mini" @click="resetQuery">重置</el-button>
        </el-form-item>
      </el-form>
    </div>

    <!-- 工具栏 -->
    <div class="toolbar">
      <el-button type="primary" icon="el-icon-plus" size="mini" @click="handleAdd"
        v-hasPermi="['system:oauth:add']">新增</el-button>
      <el-button type="success" icon="el-icon-edit" size="mini" :disabled="single" @click="handleUpdate"
        v-hasPermi="['system:oauth:edit']">修改</el-button>
      <el-button type="danger" icon="el-icon-delete" size="mini" :disabled="multiple" @click="handleDelete"
        v-hasPermi="['system:oauth:remove']">删除</el-button>
    </div>

    <!-- 数据表格 -->
    <el-table v-loading="loading" :data="oauthList" @selection-change="handleSelectionChange">
      <el-table-column type="selection" width="55" align="center" />
      <el-table-column label="ID" align="center" prop="id" width="80" />
      <el-table-column label="配置名称" align="center" prop="name" :show-overflow-tooltip="true" />
      <el-table-column label="平台类型" align="center" prop="platform" width="120">
        <template slot-scope="scope">
          <dict-tag 
            :value="scope.row.platform" 
            table-name="sys_oauth_config" 
            field-name="platform" />
        </template>
      </el-table-column>
      <el-table-column label="客户端类型" align="center" prop="client_type" width="120">
        <template slot-scope="scope">
          <dict-tag 
            :value="scope.row.client_type" 
            table-name="sys_oauth_config" 
            field-name="client_type" />
        </template>
      </el-table-column>
      <el-table-column label="AppID" align="center" prop="app_id" :show-overflow-tooltip="true" width="150" />
      <el-table-column label="状态" align="center" width="100">
        <template slot-scope="scope">
          <el-switch v-model="scope.row.status" :active-value="1" :inactive-value="0" active-color="#13ce66"
            inactive-color="#ff4949" @change="handleStatusChange(scope.row)" />
        </template>
      </el-table-column>
      <el-table-column label="排序" align="center" prop="sort_order" width="80" />
      <el-table-column label="创建时间" align="center" prop="created_at" width="160" />
      <el-table-column label="操作" align="center" width="260" class-name="small-padding fixed-width">
        <template slot-scope="scope">
          <el-button size="mini" type="text" icon="el-icon-view" @click="handleView(scope.row)"
            v-hasPermi="['system:oauth:query']">查看</el-button>
          <el-button size="mini" type="text" icon="el-icon-edit" @click="handleUpdate(scope.row)"
            v-hasPermi="['system:oauth:edit']">修改</el-button>
          <el-button size="mini" type="text" icon="el-icon-connection" @click="handleTestConnection(scope.row)"
            v-hasPermi="['system:oauth:test']">测试</el-button>
          <el-button size="mini" type="text" icon="el-icon-delete" @click="handleDelete(scope.row)"
            v-hasPermi="['system:oauth:remove']">删除</el-button>
        </template>
      </el-table-column>
    </el-table>

    <!-- 分页 -->
    <pagination v-show="total > 0" :total="total" :page.sync="queryParams.page" :limit.sync="queryParams.pageSize"
      @pagination="getList" />

    <!-- 添加或修改OAuth配置对话框 -->
    <el-dialog :title="title" :visible.sync="open" width="800px" append-to-body>
      <el-form ref="form" :model="form" :rules="rules" label-width="120px">
        <el-row>
          <el-col :span="12">
            <el-form-item label="配置名称" prop="name">
              <el-input v-model="form.name" placeholder="请输入配置名称" />
            </el-form-item>
          </el-col>
          <el-col :span="12">
            <el-form-item label="平台类型" prop="platform">
              <dict-select 
                v-model="form.platform" 
                table-name="sys_oauth_config" 
                field-name="platform"
                placeholder="请选择平台类型" 
                :disabled="!!form.id" />
            </el-form-item>
          </el-col>
        </el-row>
        <el-row>
          <el-col :span="12">
            <el-form-item label="客户端类型" prop="clientType">
              <dict-select 
                v-model="form.clientType" 
                table-name="sys_oauth_config" 
                field-name="client_type"
                placeholder="请选择客户端类型" 
                :disabled="!!form.id" />
            </el-form-item>
          </el-col>
          <el-col :span="12">
            <el-form-item label="状态" prop="status">
              <dict-select 
                v-model="form.status" 
                table-name="sys_oauth_config" 
                field-name="status"
                placeholder="请选择状态" />
            </el-form-item>
          </el-col>
        </el-row>
        <el-row>
          <el-col :span="12">
            <el-form-item label="AppID" prop="appId">
              <el-input v-model="form.appId" placeholder="请输入AppID" :disabled="!!form.id" />
            </el-form-item>
          </el-col>
          <el-col :span="12">
            <el-form-item label="排序权重" prop="sortOrder">
              <el-input-number v-model="form.sortOrder" :min="0" :max="9999" />
            </el-form-item>
          </el-col>
        </el-row>
        <el-form-item label="AppSecret" prop="appSecret">
          <el-input v-model="form.appSecret" type="password" placeholder="请输入AppSecret" show-password />
        </el-form-item>
        <el-form-item label="授权回调地址" prop="authRedirect">
          <el-input v-model="form.authRedirect" placeholder="请输入授权回调地址" />
        </el-form-item>
        <el-form-item label="授权范围" prop="scopes">
          <el-input v-model="form.scopes" placeholder="请输入授权范围，如：snsapi_userinfo" />
        </el-form-item>
        <el-form-item label="消息校验Token" prop="messageToken">
          <el-input v-model="form.messageToken" placeholder="请输入消息校验Token（可选）" />
        </el-form-item>
        <el-form-item label="消息加解密密钥" prop="messageAeskey">
          <el-input v-model="form.messageAeskey" placeholder="请输入消息加解密密钥（可选）" />
        </el-form-item>
        <el-form-item label="额外配置" prop="extraConfigText">
          <el-input v-model="form.extraConfigText" type="textarea" :rows="4"
            placeholder="请输入额外配置(JSON格式)，例如：{&quot;key1&quot;: &quot;value1&quot;, &quot;key2&quot;: &quot;value2&quot;}" />
          <div style="font-size: 12px; color: #909399; margin-top: 5px;">
            请输入有效的JSON格式，用于存储平台特定的额外配置参数
          </div>
        </el-form-item>
        <el-form-item label="描述" prop="description">
          <el-input v-model="form.description" type="textarea" placeholder="请输入描述" />
        </el-form-item>
      </el-form>
      <div slot="footer" class="dialog-footer">
        <el-button type="primary" @click="submitForm">确 定</el-button>
        <el-button @click="cancel">取 消</el-button>
      </div>
    </el-dialog>

    <!-- 查看OAuth配置对话框 -->
    <el-dialog title="OAuth配置详情" :visible.sync="viewOpen" width="800px" append-to-body>
      <div class="oauth-detail">
        <el-row class="detail-row">
          <el-col :span="12" class="detail-item">
            <span class="detail-label">配置名称:</span>
            <span class="detail-value">{{ viewForm.name }}</span>
          </el-col>
          <el-col :span="12" class="detail-item">
            <span class="detail-label">平台类型:</span>
            <span class="detail-value">
              <dict-tag 
                theme="text" 
                :value="viewForm.platform" 
                table-name="sys_oauth_config" 
                field-name="platform" />
            </span>
          </el-col>
        </el-row>
        <el-row class="detail-row">
          <el-col :span="12" class="detail-item">
            <span class="detail-label">客户端类型:</span>
            <span class="detail-value">
              <dict-tag 
                theme="text" 
                :value="viewForm.clientType" 
                table-name="sys_oauth_config" 
                field-name="client_type" />
            </span>
          </el-col>
          <el-col :span="12" class="detail-item">
            <span class="detail-label">状态:</span>
            <span class="detail-value">
              <dict-tag 
                :value="viewForm.status" 
                table-name="sys_oauth_config" 
                field-name="status" />
            </span>
          </el-col>
        </el-row>
        <el-row class="detail-row">
          <el-col :span="12" class="detail-item">
            <span class="detail-label">AppID:</span>
            <span class="detail-value">{{ viewForm.appId }}</span>
          </el-col>
          <el-col :span="12" class="detail-item">
            <span class="detail-label">排序权重:</span>
            <span class="detail-value">{{ viewForm.sortOrder }}</span>
          </el-col>
        </el-row>
        <el-row class="detail-row">
          <el-col :span="24" class="detail-item">
            <span class="detail-label">授权回调地址:</span>
            <span class="detail-value">{{ viewForm.authRedirect }}</span>
          </el-col>
        </el-row>
        <el-row class="detail-row">
          <el-col :span="12" class="detail-item">
            <span class="detail-label">授权范围:</span>
            <span class="detail-value">{{ viewForm.scopes }}</span>
          </el-col>
          <el-col :span="12" class="detail-item">
            <span class="detail-label">创建时间:</span>
            <span class="detail-value">{{ viewForm.createdAt }}</span>
          </el-col>
        </el-row>
        <el-row class="detail-row">
          <el-col :span="12" class="detail-item">
            <span class="detail-label">消息校验Token:</span>
            <span class="detail-value">{{ viewForm.messageToken || '未设置' }}</span>
          </el-col>
          <el-col :span="12" class="detail-item">
            <span class="detail-label">消息加解密密钥:</span>
            <span class="detail-value">{{ viewForm.messageAeskey || '未设置' }}</span>
          </el-col>
        </el-row>
        <el-row class="detail-row">
          <el-col :span="24" class="detail-item">
            <span class="detail-label">额外配置:</span>
            <div class="detail-value">
              <div v-if="viewForm.extraConfig && Object.keys(viewForm.extraConfig).length > 0">
                <pre class="json-preview">{{ JSON.stringify(viewForm.extraConfig, null, 2) }}</pre>
              </div>
              <span v-else style="color: #909399;">无额外配置</span>
            </div>
          </el-col>
        </el-row>
        <el-row class="detail-row">
          <el-col :span="24" class="detail-item">
            <span class="detail-label">描述:</span>
            <span class="detail-value">{{ viewForm.description || '无' }}</span>
          </el-col>
        </el-row>
      </div>
    </el-dialog>
  </div>
</template>

<script>
import {
  getOAuthConfigList,
  getOAuthConfigDetail,
  createOAuthConfig,
  updateOAuthConfig,
  deleteOAuthConfig,
  enableOAuthConfig,
  disableOAuthConfig,
  testOAuthConnection
} from '@/api/system/oauth'
import Pagination from '@/components/Pagination'
import DictSelect from '@/components/DictSelect'
import DictTag from '@/components/DictTag'

export default {
  name: 'OAuthConfig',
  components: {
    Pagination,
    DictSelect,
    DictTag
  },
  data() {
    return {
      // 遮罩层
      loading: true,
      // 选中数组
      ids: [],
      // 非单个禁用
      single: true,
      // 非多个禁用
      multiple: true,
      // 显示搜索条件
      showSearch: true,
      // 总条数
      total: 0,
      // OAuth配置表格数据
      oauthList: [],
      // 弹出层标题
      title: '',
      // 是否显示弹出层
      open: false,
      // 是否显示查看弹出层
      viewOpen: false,
      // 查询参数
      queryParams: {
        page: 1,
        pageSize: 10,
        name: null,
        platform: null,
        clientType: null,
        status: null
      },
      // 表单参数
      form: {},
      // 查看表单参数
      viewForm: {},
      // 表单校验
      rules: {
        name: [
          { required: true, message: '配置名称不能为空', trigger: 'blur' },
          { max: 100, message: '配置名称长度不能超过100个字符', trigger: 'blur' }
        ],
        platform: [
          { required: true, message: '平台类型不能为空', trigger: 'change' }
        ],
        clientType: [
          { required: true, message: '客户端类型不能为空', trigger: 'change' }
        ],
        appId: [
          { required: true, message: 'AppID不能为空', trigger: 'blur' },
          { max: 100, message: 'AppID长度不能超过100个字符', trigger: 'blur' }
        ],
        appSecret: [
          { required: true, message: 'AppSecret不能为空', trigger: 'blur' },
          { max: 100, message: 'AppSecret长度不能超过100个字符', trigger: 'blur' }
        ],
        authRedirect: [
          { type: 'url', message: '请输入有效的URL地址', trigger: 'blur' }
        ],
        description: [
          { max: 500, message: '描述长度不能超过500个字符', trigger: 'blur' }
        ],
        sortOrder: [
          { type: 'number', message: '排序权重必须是数字', trigger: 'blur' }
        ],
        extraConfigText: [
          { validator: this.validateJson, trigger: 'blur' }
        ]
      }
    }
  },
  created() {
    this.getList()
  },
  methods: {
    /** 查询OAuth配置列表 */
    getList() {
      this.loading = true
      getOAuthConfigList(this.queryParams).then(response => {
        this.oauthList = response.data.list
        this.total = response.data.total
        this.loading = false
      })
    },

    // 取消按钮
    cancel() {
      this.open = false
      this.reset()
    },
    // 表单重置
    reset() {
      this.form = {
        id: null,
        name: null,
        description: null,
        platform: null,
        clientType: null,
        appId: null,
        appSecret: null,
        authRedirect: null,
        scopes: 'snsapi_userinfo',
        messageToken: null,
        messageAeskey: null,
        extraConfigText: '',
        status: '1',
        sortOrder: 0
      }
      this.resetForm('form')
    },
    /** 搜索按钮操作 */
    handleQuery() {
      this.queryParams.page = 1
      this.getList()
    },
    /** 重置按钮操作 */
    resetQuery() {
      this.resetForm('queryForm')
      this.handleQuery()
    },
    // 表单重置
    resetForm(refName) {
      if (this.$refs[refName]) {
        this.$refs[refName].resetFields()
      }
    },
    // 多选框选中数据
    handleSelectionChange(selection) {
      this.ids = selection.map(item => item.id)
      this.single = selection.length !== 1
      this.multiple = !selection.length
    },
    /** 新增按钮操作 */
    handleAdd() {
      this.reset()
      this.open = true
      this.title = '添加OAuth配置'
    },
    /** 修改按钮操作 */
    handleUpdate(row) {
      this.reset()
      const id = row.id || this.ids
      getOAuthConfigDetail(id).then(response => {
        const config = response.data
        this.form = {
          id: config.id,
          name: config.name,
          description: config.description,
          platform: config.platform,
          clientType: config.client_type,
          appId: config.app_id,
          appSecret: config.app_secret,
          authRedirect: config.auth_redirect,
          scopes: config.scopes,
          messageToken: config.message_token,
          messageAeskey: config.message_aeskey,
          extraConfigText: config.extra_config ? JSON.stringify(config.extra_config, null, 2) : '',
          status: `${config.status}`,
          sortOrder: config.sort_order
        }
        this.open = true
        this.title = '修改OAuth配置'
      })
    },
    /** 查看按钮操作 */
    handleView(row) {
      getOAuthConfigDetail(row.id).then(response => {
        const config = response.data
        this.viewForm = {
          name: config.name,
          description: config.description,
          platform: config.platform,
          clientType: config.client_type,
          appId: config.app_id,
          authRedirect: config.auth_redirect,
          scopes: config.scopes,
          messageToken: config.message_token,
          messageAeskey: config.message_aeskey,
          extraConfig: config.extra_config,
          status: `${config.status}`,
          sortOrder: config.sort_order,
          createdAt: config.created_at
        }
        this.viewOpen = true
      })
    },
    /** 提交按钮 */
    submitForm() {
      this.$refs['form'].validate(valid => {
        if (valid) {
          // 处理额外配置JSON转换
          const submitData = { ...this.form }
          try {
            if (submitData.extraConfigText && submitData.extraConfigText.trim()) {
              submitData.extraConfig = JSON.parse(submitData.extraConfigText)
            } else {
              submitData.extraConfig = {}
            }
            delete submitData.extraConfigText
          } catch (error) {
            this.$message.error('额外配置JSON格式错误：' + error.message)
            return
          }

          if (this.form.id != null) {
            updateOAuthConfig(this.form.id, submitData).then(response => {
              this.$message.success('修改成功')
              this.open = false
              this.getList()
            })
          } else {
            createOAuthConfig(submitData).then(response => {
              this.$message.success('新增成功')
              this.open = false
              this.getList()
            })
          }
        }
      })
    },
    /** 删除按钮操作 */
    handleDelete(row) {
      const ids = row.id || this.ids
      this.$confirm('是否确认删除选中的OAuth配置？', '警告', {
        confirmButtonText: '确定',
        cancelButtonText: '取消',
        type: 'warning'
      }).then(() => {
        return deleteOAuthConfig(ids)
      }).then(() => {
        this.getList()
        this.$message.success('删除成功')
      }).catch(() => { })
    },
    /** 状态修改 */
    handleStatusChange(row) {
      let text = row.status === 1 ? '启用' : '禁用'
      this.$confirm('确认要"' + text + '""' + row.name + '"配置吗？', '提示', {
        confirmButtonText: '确定',
        cancelButtonText: '取消',
        type: 'warning'
      }).then(() => {
        return row.status === 1 ? enableOAuthConfig(row.id) : disableOAuthConfig(row.id)
      }).then(() => {
        this.$message.success(text + '成功')
      }).catch(() => {
        row.status = row.status === 0 ? 1 : 0
      })
    },
    /** 测试连接 */
    handleTestConnection(row) {
      const loading = this.$loading({
        lock: true,
        text: '正在测试连接...',
        spinner: 'el-icon-loading',
        background: 'rgba(0, 0, 0, 0.7)'
      })
      testOAuthConnection(row.id).then(response => {
        loading.close()
        if (response.data.success) {
          this.$message.success('连接测试成功')
        } else {
          this.$message.error('连接测试失败：' + response.data.message)
        }
      }).catch(() => {
        loading.close()
        this.$message.error('连接测试失败')
      })
    },

    /** 验证JSON格式 */
    validateJson(rule, value, callback) {
      if (!value || value.trim() === '') {
        callback()
        return
      }
      try {
        JSON.parse(value)
        callback()
      } catch (error) {
        callback(new Error('请输入有效的JSON格式'))
      }
    }
  }
}
</script>

<style scoped>
.search-box {
  margin-bottom: 20px;
  padding: 20px;
  background: #fff;
  border-radius: 4px;
}

.toolbar {
  margin-bottom: 20px;
}

/* OAuth详情展示样式 */
.oauth-detail {
  padding: 20px 0;
}

.detail-row {
  margin-bottom: 16px;
  border-bottom: 1px solid #ebeef5;
  padding-bottom: 16px;
}

.detail-row:last-child {
  border-bottom: none;
  margin-bottom: 0;
  padding-bottom: 0;
}

.detail-item {
  display: flex;
  align-items: flex-start;
  padding: 8px 12px;
  min-height: 40px;
}

.detail-label {
  font-weight: 600;
  color: #606266;
  margin-right: 12px;
  min-width: 120px;
  flex-shrink: 0;
  line-height: 24px;
}

.detail-value {
  color: #303133;
  flex: 1;
  line-height: 24px;
  word-break: break-all;
}

.json-preview {
  background: #f5f7fa;
  padding: 12px;
  border-radius: 4px;
  font-size: 12px;
  max-height: 200px;
  overflow-y: auto;
  margin: 8px 0;
  border: 1px solid #e4e7ed;
  white-space: pre-wrap;
  font-family: 'Monaco', 'Menlo', 'Ubuntu Mono', monospace;
}

/* 移动端适配 */
@media (max-width: 768px) {
  .detail-item {
    flex-direction: column;
    align-items: flex-start;
  }
  
  .detail-label {
    margin-bottom: 4px;
    min-width: auto;
  }
}
</style>
