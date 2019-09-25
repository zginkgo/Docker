<div class="BlogAnchor">
   <p>
   <b id="AnchorContentToggle" title="收起" style="cursor:pointer;">目录[+]</b>
   </p>
  <div class="AnchorContent" id="AnchorContent"> </div>
</div>

# centos下docker的安装

[docker官档是最好的教程](https://docs.docker.com/install/linux/docker-ce/centos/)

# 1、centos下docker的安装

## 1.1 os要求

要安装Docker CE，需要维护的CentOS 7版本，不支持或测试归档版本。centos-extras库必须启用。此存储库默认情况下处于启用状态，但如果您已禁用该存储库，则需要重新启用该存储库 。

注意要求内核版本不低于 3.10。下列命令查看：

	[root@localhost docker]# uname -r
	3.10.0-229.el7.x86_64

## 1.2 卸载旧版本

老版本的Docker被称为docker或docker-engine。如果安装了这些，请卸载它们以及相关的依赖项。

	[root@localhost docker]# yum remove docker \
	                  docker-common \
	                  docker-selinux \
	                  docker-engine

## 1.3 安装docker

**1、Docker的存储库安装**

安装Docker CE有几种不同的方式，一般`设置Docker的存储库`并从中进行安装，以方便安装和升级任务。

yum-utils provides the yum-config-manager utility, and device-mapper-persistent-data and lvm2 are required by the devicemapper storage driver.

`yum-util`提供了`yum-config-manager`的功能，`sdevice-mapper-persistent-data`和`lvm2`由`devicemapper`提供存储驱动程序。

	[root@localhost docker]# yum install -y yum-utils   device-mapper-persistent-data   lvm2

**2、设置稳定的存储库（国内yum源镜像）**

	[root@localhost docker]# yum-config-manager \
			--add-repo \
			https://mirrors.ustc.edu.cn/docker-ce/linux/centos/docker-ce.repo

**3、更新yum源缓存**

	[root@localhost docker]# yum makecache fast

**4、安装docker**

注意**不要用安装最新版本**，采的坑命令如下，正确的在后面：

	[root@localhost docker]# yum install docker-ce

这里会导致之后docker跑不起来。错误原因见：[Error response from daemon: OCI runtime create failed: unable to retrieve OCI runtime error #35972](https://github.com/moby/moby)，大体意思就是版本不支持了。

报错的原因是这样：

	docker: Error response from daemon: OCI runtime create failed: unable to retrieve OCI runtime error (open /run/docker/containerd/daemon/io.containerd.runtime.v1.linux/moby/262f67d9beb653ac60b1c7cb3b2e183d7595b4a4a93f0dcfb0ce689a588cedcd/log.json: no such file or directory): docker-runc did not terminate sucessfully: unknown.
	ERRO[0000] error waiting for container: context canceled

下面选用一个可用的版本，先说下版本号怎么查询。

在生产系统上，您应该安装特定版本的Docker CE，而不是始终使用最新版本。列出可用的版本。此示例使用该sort -r命令按版本号从最高到最低排序结果，并将其截断。

列表的内容取决于启用了哪些存储库，并且特定于您的CentOS `.el7`版本（在此示例中，由版本的后缀指示）。选择一个特定的版本进行安装。第二列是版本字符串。您可以使用整个版本字符串，但是您至少需要包含第一个连字符。第三列是存储库名称，它指明了软件包来自哪个存储库，并且通过扩展其稳定性级别。要安装特定版本，请将版本字符串附加到包名称，并用连字符（-）分隔。

> 注意：版本字符串是软件包名称加上第一个连字符的版本。在上面的例子中，完全限定的包名是docker-ce-17.06.1.ce。

查看当前可用稳定安装包。

	[root@localhost docker]# yum list docker-ce --showduplicates | sort -r
	已加载插件：fastestmirror, langpacks
	已安装的软件包
	可安装的软件包
	 * updates: mirrors.shu.edu.cn
	Loading mirror speeds from cached hostfile
	 * extras: centos.ustc.edu.cn
	docker-ce.x86_64            17.12.0.ce-1.el7.centos            docker-ce-stable 
	docker-ce.x86_64            17.09.1.ce-1.el7.centos            docker-ce-stable 
	docker-ce.x86_64            17.09.0.ce-1.el7.centos            docker-ce-stable 
	docker-ce.x86_64            17.06.2.ce-1.el7.centos            docker-ce-stable 
	docker-ce.x86_64            17.06.2.ce-1.el7.centos            @docker-ce-stable
	docker-ce.x86_64            17.06.1.ce-1.el7.centos            docker-ce-stable 
	docker-ce.x86_64            17.06.0.ce-1.el7.centos            docker-ce-stable 
	docker-ce.x86_64            17.03.2.ce-1.el7.centos            docker-ce-stable 
	docker-ce.x86_64            17.03.1.ce-1.el7.centos            docker-ce-stable 
	docker-ce.x86_64            17.03.0.ce-1.el7.centos            docker-ce-stable 

选择一个版本，具体支持最新版本到那里我没测，我安装如下：

	[root@localhost docker]# yum install docker-ce-17.06.2.ce

**5、启动和测试docker**

	[root@localhost docker]# systemctl enable docker
	[root@localhost docker]# systemctl start docker
	[root@localhost docker]# docker run hello-world

	Hello from Docker!
	This message shows that your installation appears to be working correctly.
	
	To generate this message, Docker took the following steps:
	 1. The Docker client contacted the Docker daemon.
	 2. The Docker daemon pulled the "hello-world" image from the Docker Hub.
	    (amd64)
	 3. The Docker daemon created a new container from that image which runs the
	    executable that produces the output you are currently reading.
	 4. The Docker daemon streamed that output to the Docker client, which sent it
	    to your terminal.
	
	To try something more ambitious, you can run an Ubuntu container with:
	 $ docker run -it ubuntu bash
	
	Share images, automate workflows, and more with a free Docker ID:
	 https://cloud.docker.com/
	
	For more examples and ideas, visit:
	 https://docs.docker.com/engine/userguide/

## 1.4 卸载docker

**1、卸载Docker包**

	[root@localhost docker]# yum remove docker-ce-17.06.2.ce

**2、主机上的图像，容器，卷或自定义配置文件不会自动删除。删除所有图像，容器和卷。**

	[root@localhost docker]# rm -rf /var/lib/docker

## 1.5 镜像加速器

安装docker完成之后，后续会有对镜像的下载，默认是去 Docker Hub 拉取镜像，但这里有时会因为墙遇到困难，可以配置一个镜像加速器。

对于使用 systemd 的系统，请在 /etc/docker/daemon.json 中写入如下内容（如果文件不存在请新建该文件）。

	{
		"registry-mirrors": [
			"https://registry.docker-cn.com"
		]
	}

# 2、docker工具安装

Compose 定位是：定义和运行多个 Docker 容器的应用（ Defining and running multicontainer Docker applications）。

我们知道使用一个 Dockerfile 模板文件，可以让用户很方便的定义一个单独的应用容器。然而，在日常工作中，经常会碰到需要多个容器相互配合来完成某项任务的情况。例如要实现一个 Web 项目，除了 Web 服务容器本身，往往还需要再加上后端的数据库服务容器，甚至还包括负载均衡容器等。

Compose 恰好满足了这样的需求。它允许用户通过一个单独的 docker-compose.yml 模板文件（ YAML 格式） 来定义一组相关联的应用容器为一个项目（ project）。

Compose 中有两个重要的概念：

- 服务 ( service )：一个应用的容器，实际上可以包括若干运行相同镜像的容器实例。
- 项目 ( project )：由一组关联的应用容器组成的一个完整业务单元，在 dockercompose.yml 文件中定义。

Compose 的默认管理对象是项目，通过子命令对项目中的一组容器进行便捷地生命周期管理。

## 2.1 安装docker-composer

在 Linux 上的也安装十分简单，从 官方 [GitHub Release](https://github.com/docker/compose/releases) 处直接下载编译好的二进制文件即可。

命令如下，可能会有坑，解决办法如下：

	# -L 支持转跳 --tlsv1.2 指定tls协议
	[root@localhost docker]# curl -v -L --tlsv1.2 https://github.com/docker/compose/releases/download/1.18.0/docker-compose-`uname -s`-`uname -m` -o /usr/local/bin/docker-compose
	
	[root@localhost docker]# chmod +x /usr/local/bin/docker-compose

[curl: (35) Peer reports incompatible or unsupported protocol version.](https://github.com/voxpupuli/puppet-archive/issues/273)

## 2.2 使用docker-composer

Compose文件是一个定义服务，网络和卷的YAML文件。Compose文件的默认路径是`./docker-compose.yml`。











































