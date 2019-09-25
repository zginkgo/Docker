FROM php:7.1-fpm
MAINTAINER daocoder 327216245@qq.com

# 时区配置
ENV TIMEZONE Asia/Shanghai
# RUN ln -snf /usr/share/zoneinfo/$TIMEZONE /etc/localtime
RUN echo $TIMEZONE > /etc/timezone
# RUN usermod -u 1000 www-data

# 替换国内镜像
COPY sources.list /etc/apt/sources.list

RUN docker-php-ext-install mbstring opcache pdo pdo_mysql mysqli
# 添加扩展 gd zip 相关依赖必须手动安装
RUN apt-get update && apt-get install -y \
        libfreetype6-dev \
        libjpeg62-turbo-dev \
        libmcrypt-dev \
        libpng-dev \
    && docker-php-ext-install -j2 iconv mcrypt \
    && docker-php-ext-configure gd \
        --with-freetype-dir=/usr/include/ \
        --with-png-dir=/usr/include/ \
        --with-jpeg-dir=/usr/include/ \
        --with-zlib-dir=/usr \
    && docker-php-ext-install -j2 gd zip

# 添加redis
RUN pecl install redis-3.1.6 \
    && docker-php-ext-enable redis

# 添加memcached
RUN apt-get install -y libmemcached-dev zlib1g-dev \
    && pecl install memcached-3.0.4 \
    && docker-php-ext-enable memcached

# 添加memcache
# RUN pecl install memcache-3.0.8 \
#     && docker-php-ext-enable memcache
# 
# WORKDIR /www