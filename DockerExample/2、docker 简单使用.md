<div class="BlogAnchor">
   <p>
   <b id="AnchorContentToggle" title="收起" style="cursor:pointer;">目录[+]</b>
   </p>
  <div class="AnchorContent" id="AnchorContent"> </div>
</div>

# docker命令简单使用

## 1.1 镜像

Docker 运行容器前需要本地存在对应的镜像，如果本地不存在该镜像，Docker会从镜像仓库下载该镜像。如github一般，docker有一个公用镜像仓库[docker hub](https://hub.docker.com)。

### 1.1.1 镜像的拉取

从 Docker 镜像仓库获取镜像的命令是 docker pull 。其命令格式为：

	docker pull [选项] [Docker Registry 地址[:端口号]/]仓库名[:标签]

具体的选项可以通过 docker pull --help 命令看到，这里我们说一下镜像名称的格式。

- Docker镜像仓库地址：地址的格式一般是 <域名/IP>[:端口号] 。默认地址是 Docker Hub。
- 仓库名：如之前所说，这里的仓库名是两段式名称，即 <用户名>/<软件名> 。对于 Docker Hub，如果不给出用户名，则默认为 library ，也就是官方镜像。

从下载过程中可以看到我们之前提及的分层存储的概念，镜像是由多层存储所构成。下载也是一层层的去下载，并非单一文件。下载过程中给出了每一层的 ID 的前 12 位。并且下载结束后，给出该镜像完整的 sha256 的摘要，以确保下载一致性。

### 1.1.2 镜像的查看

要想列出已经下载下来的镜像，可以使用 docker image ls 命令。列表包含了仓库名、标签、镜像 ID 、创建时间以及所占用的空间 。

	[root@localhost conf]# docker image ls -a
	REPOSITORY          TAG                 IMAGE ID            CREATED             SIZE
	nginx               1.13                e548f1a579cf        3 days ago          109MB
	mysql               5.7                 f0f3956a9dd8        6 days ago          409MB
	memcached           1.5                 9a7e8440a999        6 days ago          58.6MB
	php                 7.1-fpm             5f2501864f65        7 days ago          382MB
	redis               3.2                 3859b0a6622a        8 days ago          99.7MB

### 1.1.3 利用commit理解镜像构成

镜像是容器的基础，每次执行 docker run 的时候都会指定哪个镜像作为容器运行的基础。在之前的例子中，我们所使用的都是来自于 Docker Hub 的镜像。直接使用这些镜像是可以满足一定的需求，而当这些镜像无法直接满足需求时，我们就需要定制这些镜像。

镜像是多层存储，每一层是在前一层的基础上进行的修改；而容器同样也是多层存储，是在以镜像为基础层，在其基础上加一层作为容器运行时的存储
层。

假如我们定制好了变化，我们希望能将其保存下来形成镜像。要知道，当我们运行一个容器的时候（ 如果不使用卷的话） ，我们做的任何文件修改都会被记录于容器存储层里。而 Docker 提供了一个 docker commit 命令，可以将容器的存储层保存下来成为镜像。换句话说，就是在原有镜像的基础上，再叠加上容器的存储层，并构成新的镜像。以后我们运行这个新镜像的时候，就会拥有原有容器最后的文件变化。

docker commit 的语法格式为：

	docker commit [选项] <容器ID或容器名> [<仓库名>[:<标签>]]

我们还可以用 docker history 具体查看镜像内的历史记录。

**慎用docker commit**

首先，由于修改命令的执行，还有很多文件被改动或添加了。这还仅仅是最简单的操作，如果是安装软件包、编译构建，那会有大量的无关内容被添
加进来，如果不小心清理，将会导致镜像极为臃肿。

此外，使用 docker commit 意味着所有对镜像的操作都是黑箱操作，生成的镜像也被称为黑箱镜像，换句话说，就是除了制作镜像的人知道执行过什么命令、怎么生成的镜像，别人根本无从得知。而且，即使是这个制作镜像的人，过一段时间后也无法记清具体在操作的。虽然 docker diff 或许可以告诉得到一些线索，但是远远不到可以确保生成一致镜像的地步。这种黑箱镜像的维护工作是非常痛苦的。

而且，回顾之前提及的镜像所使用的分层存储的概念，除当前层外，之前的每一层都是不会发生改变的，换句话说，任何修改的结果仅仅是在当前层进行标记、添加、修改，而不会改动上一层。如果使用 docker commit 制作镜像，以及后期修改的话，每一次修改都会让镜像更加臃肿一次，所删除的上一层的东西并不会丢失，会一直如影随形的跟着这个镜像，即使根本无法访问到。这会让镜像更加臃肿。

### 1.1.4 使用 Dockerfile 定制镜像

我们可以了解到，镜像的定制实际上就是定制每一层所添加的配置、文件。如果我们可以把每一层修改、安装、构建、操作的命令都写入一个脚本，用这个脚本来构建、定制镜像，那么之前提及的无法重复的问题、镜像构建透明性的问题、体积的问题就都会解决。这个脚本就是 Dockerfile。

Dockerfile 是一个文本文件，其内包含了一条条的指令(Instruction)，每一条指令构建一层，因此每一条指令的内容，就是描述该层应当如何构建。

Dockerfile文件的命令及使用这里不做介绍，下面会给出链接地址，以上及此部分的相关内容都是从中获取，可自行参阅。

### 1.1.5 删除本地镜像

如果要删除本地的镜像，可以使用 docker image rm 命令，其格式为：

	docker image rm [选项] <镜像1> [<镜像2> ...]

## 2.1 容器

### 2.1.1 启动容器

	Usage:	docker run [OPTIONS] IMAGE [COMMAND] [ARG...]

启动容器有两种方式，一种是基于镜像新建一个容器并启动，另外一个是将在终止状（stopped ）的容器重新启动。因为docker的容器实在太轻量级了，很多时候用户都是随时删除和新创建容器。

**1、新建容器并启动**

	$ docker run [container ID or NAMES]

下面的命令则启动一个 bash 终端，允许用户进行交互。

	$ docker run -t -i ubuntu:14.04 /bin/bash
	root@af8bae53bdd3:/#

-t 选项让docker分配一个伪终端（ pseudo-tty） 并绑定到容器的标准输入上，-i 则让容器的标准输入保持打开。

当利用 docker run 来创建容器时，Docker 在后台运行的标准操作包括：

- 检查本地是否存在指定的镜像，不存在就从公有仓库下载
- 利用镜像创建并启动一个容器
- 分配一个文件系统，并在只读的镜像层外面挂载一层可读写层
- 从宿主主机配置的网桥接口中桥接一个虚拟接口到容器中去
- 从地址池配置一个 ip 地址给容器
- 执行用户指定的应用程序
- 执行完毕后容器被终止

**2、终止已启动的容器**

将一个终止的容器启动。

	$ docker container start [container ID or NAMES]

### 2.1.2 容器后台运行

更多的时候，需要让docker在后台运行而不是直接把执行命令的结果输出到当前宿主主机下。可通过-d参数来实现。

此时容器会在后台运行并不会把输出的结果（STDOUT）打印到宿主主机上面（结果可用docker logs查看）。

	$ docker container logs [container ID or NAMES]

### 2.1.3 终止容器

可以使用 docker container stop 来终止一个运行中的容器。此外，当 Docker 容器中指定的应用终结时，容器也自动终止。

	$ docker container stop [container ID or NAMES]

容器列表可以用 docker container ls -a 命令看到。

	$ docker container ls -a 

docker container restart 命令会将一个运行态的容器终止，然后再重新启动它。

	$ docker container restart

### 2.1.4 进入容器

在使用-d参数时，容器启动后会进入后台。

某些时候需要进入容器进行操作，包括使用docker attach命令或docker exec命令，推荐使用docker exec命令。

**1、attach命令**：docker attach 是 docker 自带的命令。

	$ docker attach [container ID or NAMES]

如果从这个 stdin 中 exit，会导致容器的停止。


**2、exec命令**

docker exec 后边可以跟多个参数，这里主要说明 -i -t 参数。只用 -i 参数时，由于没有分配伪终端，界面没有我们熟悉的 Linux 命令提示符，但命令执行结果仍然可以返回。当 -i -t 参数一起使用时，则可以看到我们熟悉的 Linux 命令提示符。

	$ docker exec -it [container ID or NAMES] bash

如果从这个 stdin 中 exit，不会导致容器的停止。这就是推荐使用 docker exec 的原因。

### 2.1.5 导入/出容器

**1、导出容器**

如果要导出本地某个容器，可以使用 docker export 命令。

	$ docker ps -a
	CONTAINER ID        IMAGE               COMMAND                  CREATED             STATUS              PORTS                NAMES
	045f12b97d48        nginx:latest        "nginx -g 'daemon ..."   3 days ago          Up 3 days           0.0.0.0:80->80/tcp   nginx

	$ docker export 045f12b97d48 > nginx.tar

**2、导入容器快照**

**可以使用 docker import 从容器快照文件中再导入为镜像。**

	$ cat nginx.tar | docker import - test/nginx:latest

此外，也可以通过指定 URL 或者某个目录来导入。

	$ docker import http://example.com/exampleimage.tgz example/imagerepo

用户既可以使用 docker load 来导入镜像存储文件到本地镜像库，也可以使用 docker import 来导入一个容器快照到本地镜像库。这两者的区别在于容器快照文件将丢弃所有的历史记录和元数据信息（ 即仅保存容器当时的快照状态），而镜像存储文件将保存完整记录，体积也要大。此外，从容器快照文件导入时可以重新指定标签等元数据信息。

### 2.1.6 删除容器

1、单个删除

	docker container rm [container ID or NAMES]

如果要删除一个运行中的容器，可以添加 -f 参数。Docker 会发送 SIGKILL 信号给容器。

2、删除全部终止容器

用 docker container ls -a 命令可以查看所有已经创建的包括终止状态的容器，如果数量太
多要一个个删除可能会很麻烦，用下面的命令可以清理掉所有处于终止状态的容器。

	docker container prune

以上全部参阅[docker入门到实践.pdf](https://github.com/mudaixiansheng/docker/blob/master/docker%E5%85%A5%E9%97%A8%E5%88%B0%E5%AE%9E%E8%B7%B5.pdf)一书，感谢其作者，链接仅供学习交流使用。















