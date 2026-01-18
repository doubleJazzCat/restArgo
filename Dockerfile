# 基础镜像：基于 Alpine Linux 的 PHP 8.2 CLI (极简体积)
FROM php:8.2-cli-alpine

# 设置工作目录
WORKDIR /var/www/html

# 安装系统依赖并编译 PHP 扩展
# 使用虚拟组 (.build-deps) 在编译后自动清理开发库，保持镜像轻量
RUN apk add --no-cache libstdc++ sqlite-libs libcurl \
    && apk add --no-cache --virtual .build-deps curl-dev sqlite-dev \
    && docker-php-ext-install pdo pdo_mysql pdo_sqlite curl \
    && apk del .build-deps

# 复制项目源代码
COPY . /var/www/html/

# 初始化配置文件 (如果存在模版则自动复制)
RUN if [ -f config-sample.php ]; then cp config-sample.php config.php; fi

# 创建数据目录并授权 (确保无权限问题)
RUN mkdir -p data && chmod -R 777 data

# 暴露 HTTP 端口
EXPOSE 80

# 启动命令：使用 PHP 内置服务器并挂载路由脚本
CMD ["php", "-S", "0.0.0.0:80", "-t", "/var/www/html", "router.php"]