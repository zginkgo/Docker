<div class="BlogAnchor">
   <p>
   <b id="AnchorContentToggle" title="收起" style="cursor:pointer;">目录[+]</b>
   </p>
  <div class="AnchorContent" id="AnchorContent"> </div>
</div>

# docker lnmp环境搭建

**提示：本文可能只对lnmp环境有一定了解的基础有交流学习的价值。**

## 1、下载相应镜像到本地

	[root@localhost lnmp]# docker pull php:7.1-fpm
	[root@localhost lnmp]# docker pull mysql:5.7
	[root@localhost lnmp]# docker pull nginx:1.13
	[root@localhost lnmp]# docker pull redis:3.2
	[root@localhost lnmp]# docker pull memcached:1.5

## 2、创建相应容器

	# 创建php:7.1-fpm的容器并命名php7.1，将容器的9000端口映射到主机的9000端口。把主机的/home/lnmp/www/目录挂载到容器的/www目录（这个目录用于存放php脚本文件）
	[root@localhost lnmp]# docker run -d -p 9000:9000 --name php7.1 -v /home/lnmp/www/:/www php:7.1-fpm

	# 创建nginx:1.13的容器并命名nginx1.13，将容器的80端口映射到主机的80端口。把主机的/home/lnmp/app/nginx1.13/conf/目录挂载到容器的/etc/nginx/conf.d目录；/home/lnmp/www/目录挂载到容器的/www目录。
	[root@localhost lnmp]# docker run -d -p 80:80 --name nginx1.13 -v /home/lnmp/app/nginx1.13/conf/:/etc/nginx/conf.d -v /home/lnmp/www/:/www nginx:1.13 

	# 创建mysql:5.7的容器并命名mysql5.7，将容器的3306端口映射到主机的3306端口。把主机的/home/lnmp/data/mysql目录挂载到容器的/var目录。设置root的密码为123456。
	[root@localhost lnmp]# docker run -d -p 3306:3306 --name mysql5.7 -v /home/lnmp/data/mysql:/var/lib/mysql -e MYSQL_ROOT_PASSWORD=123456 mysql:5.7

	# 创建redis:3.2的容器并命名redis3.2，将容器的6379端口映射到主机的6379端口。把主机的/home/lnmp/data/redis目录挂载到容器的/data目录。设置redis的持久化保存。
	[root@localhost lnmp]# docker run -d -p 6379:6379 --name redis3.2 -v /home/lnmp/data/redis:/data redis:3.2 redis-server --appendonly yes

	# 创建memcached:1.5的容器并命名memcached1.5，将容器的11211端口映射到主机的11211端口。设置最大内存空间64M。
	[root@localhost lnmp]# docker run -d -p 11211:11211 --name memcached1.5 memcached:1.5 -m 64

## 3、相关文件的配置

### 3.1 nignx的配置

在站点目录下（即lnmp环境搭建的目录），结构之后可见github链接地址。

下面以绝对地址说明：在/home/lnmp/app/nginx1.13/conf/目录下新建nginx.conf文件，保持nginx原有文件的面貌，配置如下：

	server {
	    listen       80;
	    server_name  localhost;
	    index  index.php index.html index.htm;
	    root   /www/testweb/project/ac;
	    #charset koi8-r;
	    #access_log  /var/log/nginx/host.access.log  main;
	
	    location / {
		try_files $uri $uri/ /index.php?$query_string;
		index  index.php index.html index.htm;
	        # root   /usr/share/nginx/html;
	        root   /www/testweb/project/ac;
	    }
	
	    #error_page  404              /404.html;
	
	    # redirect server error pages to the static page /50x.html
	    #
	    error_page   500 502 503 504  /50x.html;
	    location = /50x.html {
	        root   /www;
	    }
	
	    # proxy the PHP scripts to Apache listening on 127.0.0.1:80
	    #
	    #location ~ \.php$ {
	    #    proxy_pass   http://127.0.0.1;
	    #}
	
	    # pass the PHP scripts to FastCGI server listening on 127.0.0.1:9000
	    # 关键点在这里，下面会有说明。
	    location ~ [^/]\.php(/|$) {
	        fastcgi_pass   172.17.0.2:9000; # 这里的ip是对应PHP版本的容器的ip地址，这个为容器之间的通信，下节会有介绍
	        fastcgi_index  index.php;
	        fastcgi_param  SCRIPT_FILENAME $document_root$fastcgi_script_name;
	        include        fastcgi_params;
	    }
	
	    # deny access to .htaccess files, if Apache's document root
	    # concurs with nginx's one
	    #
	    #location ~ /\.ht {
	    #    deny  all;
	    #}
	}

在/home/lnmp/app/nginx1.13/conf/目录下新建fastcgi_params文件，配置如下：

	fastcgi_param  SCRIPT_FILENAME    $document_root$fastcgi_script_name;
	fastcgi_param  PATH_INFO          $fastcgi_script_name;  
	fastcgi_param  QUERY_STRING       $query_string;
	fastcgi_param  REQUEST_METHOD     $request_method;
	fastcgi_param  CONTENT_TYPE       $content_type;
	fastcgi_param  CONTENT_LENGTH     $content_length;
	
	fastcgi_param  SCRIPT_NAME        $fastcgi_script_name;
	fastcgi_param  REQUEST_URI        $request_uri;
	fastcgi_param  DOCUMENT_URI       $document_uri;
	fastcgi_param  DOCUMENT_ROOT      $document_root;
	fastcgi_param  SERVER_PROTOCOL    $server_protocol;
	fastcgi_param  REQUEST_SCHEME     $scheme;
	fastcgi_param  HTTPS              $https if_not_empty;
	
	fastcgi_param  GATEWAY_INTERFACE  CGI/1.1;
	fastcgi_param  SERVER_SOFTWARE    nginx/$nginx_version;
	
	fastcgi_param  REMOTE_ADDR        $remote_addr;
	fastcgi_param  REMOTE_PORT        $remote_port;
	fastcgi_param  SERVER_ADDR        $server_addr;
	fastcgi_param  SERVER_PORT        $server_port;
	fastcgi_param  SERVER_NAME        $server_name;
	
	# PHP only, required if PHP was built with --enable-force-cgi-redirect
	fastcgi_param  REDIRECT_STATUS    200;

虚拟主机等配置见docker-composer.yml。

这里说明一些东西：

Nginx 调用 fpm 服务是通过 fastcgi 参数进行的。如通过 SCRIPT_FILENAME 参数指定要加载的文件路径。
 
- fastcgi_pass：指定 fpm 服务的调用地址，即nginx设置的反向代理地址，只需要把容器中的9000端口映射到宿主机的9000端口上就可以了。
- fastcgi_index：默认文件。
- fastcgi_param：每一个 fastcgi_param 指令都定义了一个会发送给 cgi 进程的参数，打开 Nginx 配置目录中的 fastcgi_params 文件可以看到里面定义了很多参数。其中，`SCRIPT_FILENAME` 对我们来说算是最重要的。 

>`SCRIPT_FILENAME` 指令指定了 cgi 进程需要加载的文件路径。例如用户访问 http://xxx.com/a.php，Nginx 中将会处理此次请求。Nginx 判断后缀名是 .php 的请求后将会把此次请求转发给 cgi 进程处理，即 fastcgi_pass；转发的过程中会携带一些和访问相关的参数或其它预设的参数（fastcgi_param），然而这个 cgi 进程（PHP FPM）并不知道要加载的文件在哪里，这便是 SCRIPT_FILENAME 的作用了。
 
>简单的说，配置 SCRIPT_FILENAME 的值就是要做到 FPM 进程能找到这个文件就可以了。例如代码目录存放在宿主机的 /home/www 目录下，我们使用 -v 命令启动 docker 时把代码目录映射到了容器内部的 /var/www/html 目录下：
 
>$ docker run -d -p 9000:9000 -v /home/www:/var/www/html php:7.0-fpm
 因为 fpm 进程是运行在容器里面的，所以 SCRIPT_FILENAME 查找的路径一定是在容器内能找到的，即：
 
>fastcgi_param  SCRIPT_FILENAME  /var/www/html/$fastcgi_script_name;
至此应该全明白了吧，Nginx 配置中的 SCRIPT_FILENAME 要和容器中保持一致才行。当然也可以让容器中的目录结构保持与宿主机中一致，即 -v /home/www:/home/www，这样配置的时候可能会方便一些，不会出现因目录不一致而出错的机率。
 
 

### 3.2 php的扩展的配置

[官网文档是最好的说明](https://hub.docker.com/_/php/)。

打开链接转`How to install more PHP extensions`。

在介绍一个网上看着比较好的博文[Docker 中的 PHP 如何安装扩展](https://my.oschina.net/antsky/blog/1591418)，有时打开慢，这里再搬来下。

**1、PHP 源码**

为了保证 Docker 镜像尽量小，PHP 的源文件是以压缩包的形式存在镜像中，官方提供了 docker-php-source 快捷脚本，用于对源文件压缩包的解压（extract）及解压后的文件进行删除（delete）的操作。

	FROM php:7.1-apache
	RUN docker-php-source extract \
	    # 此处开始执行你需要的操作 \
	    && docker-php-source delete

**注意：一定要记得删除，否则解压出来的文件会大大增加镜像的文件大小。**

**2、安装扩展**

a、核心扩展

这里主要用到的是官方提供的 docker-php-ext-configure 和 docker-php-ext-install 快捷脚本，如下

	FROM php:7.1-fpm
	RUN apt-get update \
		# 相关依赖必须手动安装
		&& apt-get install -y \
	        libfreetype6-dev \
	        libjpeg62-turbo-dev \
	        libmcrypt-dev \
	        libpng-dev \
	    # 安装扩展
	    && docker-php-ext-install -j$(nproc) iconv mcrypt \
	    # 如果安装的扩展需要自定义配置时
	    && docker-php-ext-configure gd --with-freetype-dir=/usr/include/ --with-jpeg-dir=/usr/include/ \
	    && docker-php-ext-install -j$(nproc) gd

**注意：这里的 docker-php-ext-configure 和 docker-php-ext-install 已经包含了 docker-php-source 的操作，所有不需要再手动去执行。**

b、PECL 扩展

因为一些扩展并不包含在 PHP 源码文件中，所有需要使用 PECL（PHP 的扩展库仓库，通过 PEAR 打包）。用 pecl install 安装扩展，然后再用官方提供的 docker-php-ext-enable 快捷脚本来启用扩展，如下示例：

	FROM php:7.1-fpm
	RUN apt-get update \
		# 手动安装依赖
		&& apt-get install -y libmemcached-dev zlib1g-dev \
		# 安装需要的扩展
	   && pecl install memcached-2.2.0 \
	   # 启用扩展
	   && docker-php-ext-enable memcached

c、其它扩展

一些既不在 PHP 源码包，也不再 PECL 扩展仓库中的扩展，可以通过下载扩展程序源码，编译安装的方式安装，如下示例：

	FROM php:5.6-apache
	RUN curl -fsSL 'https://xcache.lighttpd.net/pub/Releases/3.2.0/xcache-3.2.0.tar.gz' -o xcache.tar.gz \
	    && mkdir -p xcache \
	    && tar -xf xcache.tar.gz -C xcache --strip-components=1 \
	    && rm xcache.tar.gz \
	    && ( \
	        cd xcache \
	        && phpize \
	        && ./configure --enable-xcache \
	        && make -j$(nproc) \
	        && make install \
	    ) \
	    && rm -r xcache \
	    && docker-php-ext-enable xcache

**注意：官方提供的 docker-php-ext-* 脚本接受任意的绝对路径（不支持相对路径，以便与系统内置的扩展程序进行区分）**。

所以，上面的例子也可以这样写：
	
	FROM php:5.6-apache
	RUN curl -fsSL 'https://xcache.lighttpd.net/pub/Releases/3.2.0/xcache-3.2.0.tar.gz' -o xcache.tar.gz \
	    && mkdir -p /tmp/xcache \
	    && tar -xf xcache.tar.gz -C /tmp/xcache --strip-components=1 \
	    && rm xcache.tar.gz \
	    && docker-php-ext-configure /tmp/xcache --enable-xcache \
	    && docker-php-ext-install /tmp/xcache \
	    && rm -r /tmp/xcache

这里`make -j$(nproc)`这个数字好像是指cpu的核数，也没找到具体的相关说明。

### 3.3 其它相关的配置

没啥好说的，以后来补充。

## 4、容器之间的通信

**1、获取各个容器的ip地址：两种方法**

a、参考博文：[如何获取 docker 容器(container)的 ip 地址](http://blog.csdn.net/sannerlittle/article/details/77063800)

	[root@localhost conf]# docker inspect -f '{{.Name}} - {{range .NetworkSettings.Networks}}{{.IPAddress}}{{end}}' $(docker ps -aq)
	/php7.1 - 172.18.0.4
	/redis3.2 - 172.18.0.5
	/mysql5.7 - 172.18.0.3
	/memcached1.5 - 172.18.0.6
	/nginx1.13 - 172.18.0.2

b、装完容器后，利用iptables

	[root@localhost conf]# iptables -L --line-number
	Chain DOCKER (2 references)
	num  target     prot opt source               destination         
	1    ACCEPT     tcp  --  anywhere             172.18.0.2           tcp dpt:http					# nginx
	2    ACCEPT     tcp  --  anywhere             172.18.0.3           tcp dpt:mysql				# mysql
	3    ACCEPT     tcp  --  anywhere             172.18.0.4           tcp dpt:cslistener			# php 这里就是上面nginx配置需要写的ip地址
	4    ACCEPT     tcp  --  anywhere             172.18.0.5           tcp dpt:6379					# redis
	5    ACCEPT     tcp  --  anywhere             172.18.0.6           tcp dpt:memcache				# memcache

上面已经说过nginx配置需要的php-fpm的ip地址就是`dpt:cslistener`对应的地址。其它扩展对应的地址在代码中填写连接即可，例我的代码如下：

	<?php 
	return [
	    'file'=>[
	        'type'=>'file',
	        'debug'=>true,
	        'pconnect'=>0,
	        'autoconnect'=>0,
	    ],
	    'memcache'=>[
	        'hostname'=>'172.17.0.6',
	        'port'=>11211,
	        'type'=>'memcache',
	        'debug'=>true,
	        'pconnect'=>0,
	        'autoconnect'=>0,
	        'pre'=>'@ns_ls_',
	        'ismaster'=>true,
	    ],
	    'memcached'=>[
	        'hostname'=>'172.17.0.6',
	        'port'=>11211,
	        'type'=>'memcached',
	        'debug'=>true,
	        'pconnect'=>0,
	        'autoconnect'=>0,
	        'pre'=>'@ns_ls_',
	        'ismaster'=>true,
	    ],
	    'redis'=>[
	        'hostname'=>'172.17.0.5',
	        'port'=>6379,
	        'timeout'=>0,
	        'type'=>'redis',
	        'debug'=>true,
	        'pconnect'=>0,
	        'autoconnect'=>0,
	        'pre'=>'@ns_ls_',
	        'isusecluster'=>false,
	    ],
	    'apc'=>[
	        'type'=>'apc',
	        'pre'=>'@ns_ls_',
	    ],
	    'xcache'=>[
	        'type'=>'xcache',
	        'pre'=>'@ns_ls_',
	    ],
	    'eaccelerator'=>[
	        'type'=>'eaccelerator',
	        'pre'=>'@ns_ls_',
	    ],
	    '__pre'=>'TnPGcc_',
	];

## 5、一些采坑的记录及建议

**1、docker容器卷印射错误**

	[root@localhost ~]# docker run -d -p 9000:9000 --name php7.1 -v /home/wwwroot/:/var/www/ php:7.1-fpm
	71f401361040492798a2deab03ccc86025fbd521f2db6308679ef13109162974
	docker: Error response from daemon: oci runtime error: container_linux.go:262: starting container process caused "chdir to cwd (\"/var/www/html\") set in config.json failed: no such file or directory".

[工作目录途径导致](https://github.com/moby/moby/issues/26855)

**2、nginx无法解析php文件或输出页为空白**

	[error] 5#5: *3 FastCGI sent in stderr: "Primary script unknown" while reading response header from upstream

解决：

    location ~ [^/]\.php(/|$) {
        fastcgi_pass   172.17.0.4:9000;
        fastcgi_index  index.php;
        fastcgi_param  SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include        fastcgi_params;
    }
    
**3、mysql容器启动报错**

卷印射目录即`/data/mysql/`，这个目录是一个空目录。

建议：

- 单个容器一个个跑着安装积累经验，最后全部删除统一安装。
- 注意过程中的资料查找及笔记记录。
- 官档是最好的说明。





