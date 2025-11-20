# 部署打包说明

## 打包前的准备

### 1. 清理不必要的文件

打包前，请删除或忽略以下文件/目录：

```
runtime/log/*          # 日志文件（保留目录）
runtime/cache/*        # 缓存文件（保留目录）
runtime/temp/*         # 临时文件（保留目录）
public/uploads/*       # 上传的文件（可选，保留目录结构）
vendor/                # 依赖包（需要重新安装）
composer.lock          # 锁定文件（可选）
```

### 2. 保留的目录结构

确保以下目录存在（即使为空）：
```
public/uploads/covers/
public/uploads/videos/
runtime/log/
runtime/cache/
runtime/temp/
```

## 打包方式

### 方式一：使用压缩工具打包

1. **Windows系统**
   - 选中项目文件夹
   - 右键 -> 发送到 -> 压缩(zipped)文件夹
   - 或使用 WinRAR/7-Zip 压缩

2. **Linux/Mac系统**
   ```bash
   cd /path/to/parent/directory
   tar -czf videotool-v1.0.0.tar.gz videotool/
   # 或
   zip -r videotool-v1.0.0.zip videotool/
   ```

### 方式二：使用Git打包（推荐）

如果使用Git管理代码：

```bash
# 创建发布标签
git tag -a v1.0.0 -m "版本1.0.0发布"
git push origin v1.0.0

# 打包
git archive --format=zip --output=videotool-v1.0.0.zip v1.0.0
```

## 打包文件清单

### 必需文件

```
videotool/
├── app/                    # 应用代码
│   ├── BaseController.php
│   ├── common.php
│   ├── controller/
│   ├── model/
│   └── middleware/
├── config/                 # 配置文件
├── database/               # 数据库文件
│   ├── schema.sql
│   └── fix_charset.sql
├── public/                 # 入口文件
│   ├── index.php
│   ├── admin.php
│   ├── .htaccess
│   └── uploads/           # 目录（可为空）
├── route/                  # 路由文件
├── view/                   # 视图文件
├── runtime/                # 运行时目录（可为空）
│   ├── log/
│   ├── cache/
│   └── temp/
├── composer.json           # 依赖配置
├── composer.phar           # Composer（可选）
├── README.md               # 说明文档
├── INSTALL.md              # 安装文档
├── DEPLOY.md               # 部署文档
└── .gitignore              # Git忽略文件
```

### 不需要打包的文件

```
vendor/                     # 依赖包（需要重新安装）
composer.lock               # 锁定文件（可选）
runtime/log/*.log           # 日志文件
runtime/cache/*             # 缓存文件
runtime/temp/*              # 临时文件
public/uploads/*            # 上传的文件（可选）
.git/                       # Git目录
.idea/                      # IDE配置
*.log                       # 日志文件
.DS_Store                   # Mac系统文件
Thumbs.db                   # Windows缩略图
```

## 部署步骤

### 1. 上传文件

将打包的文件上传到服务器：

```bash
# 使用FTP/SFTP上传
# 或使用SCP
scp videotool-v1.0.0.zip user@server:/var/www/
```

### 2. 解压文件

```bash
cd /var/www/
unzip videotool-v1.0.0.zip
# 或
tar -xzf videotool-v1.0.0.tar.gz
```

### 3. 安装依赖

```bash
cd videotool
composer install --no-dev --optimize-autoloader
```

### 4. 配置环境

按照 `INSTALL.md` 中的步骤进行配置：
- 配置数据库
- 导入数据库
- 设置目录权限
- 配置Web服务器

### 5. 测试访问

- 前台：`http://your-domain.com/`
- 后台：`http://your-domain.com/admin.php`

## 快速部署脚本

### Linux部署脚本

创建 `deploy.sh`：

```bash
#!/bin/bash

# 项目路径
PROJECT_PATH="/var/www/videotool"
# 数据库配置
DB_HOST="127.0.0.1"
DB_NAME="videotool"
DB_USER="root"
DB_PASS="your_password"

echo "开始部署..."

# 1. 进入项目目录
cd $PROJECT_PATH

# 2. 安装依赖
echo "安装PHP依赖..."
composer install --no-dev --optimize-autoloader

# 3. 设置权限
echo "设置目录权限..."
chmod -R 777 public/uploads runtime
chown -R www-data:www-data public/uploads runtime

# 4. 导入数据库（如果需要）
# echo "导入数据库..."
# mysql -u$DB_USER -p$DB_PASS $DB_NAME < database/schema.sql

# 5. 清理缓存
echo "清理缓存..."
rm -rf runtime/cache/*
rm -rf runtime/temp/*

echo "部署完成！"
```

使用：
```bash
chmod +x deploy.sh
./deploy.sh
```

## 版本管理

### 版本号规则

采用语义化版本号：`主版本号.次版本号.修订号`

- **主版本号**: 不兼容的API修改
- **次版本号**: 向下兼容的功能性新增
- **修订号**: 向下兼容的问题修正

### 发布清单

每次发布应包含：
1. 版本号
2. 更新日志（CHANGELOG.md）
3. 数据库变更说明（如有）
4. 升级指南（如有重大变更）

## 备份建议

### 定期备份

1. **数据库备份**
   ```bash
   mysqldump -u root -p videotool > backup_$(date +%Y%m%d).sql
   ```

2. **文件备份**
   ```bash
   tar -czf backup_files_$(date +%Y%m%d).tar.gz public/uploads/
   ```

3. **完整备份**
   ```bash
   tar -czf backup_full_$(date +%Y%m%d).tar.gz \
       --exclude='vendor' \
       --exclude='runtime/cache' \
       --exclude='runtime/temp' \
       videotool/
   ```

### 备份恢复

1. **恢复数据库**
   ```bash
   mysql -u root -p videotool < backup_20251120.sql
   ```

2. **恢复文件**
   ```bash
   tar -xzf backup_files_20251120.tar.gz
   ```

---

**部署完成后，请按照 INSTALL.md 进行配置和测试！**

