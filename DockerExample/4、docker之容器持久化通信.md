<div class="BlogAnchor">
   <p>
   <b id="AnchorContentToggle" title="收起" style="cursor:pointer;">目录[+]</b>
   </p>
  <div class="AnchorContent" id="AnchorContent"> </div>
</div>

# docker之容器通信

**这节属于了解学习，算是烂尾，最后我也没找到合适的方式去固定容器ip，然后作为正式环境去跑，希望有解决办法的私信，带带我这渣渣。**

# 1、docker的网络配置介绍

## 1.1 引子

当 Docker 启动时，会自动在主机上创建一个 docker0 虚拟网桥，实际上是 Linux 的一个bridge，可以理解为一个软件交换机。它会在挂载到它的网口之间进行转发。

同时，Docker 随机分配一个本地未占用的私有网段（ 在 RFC1918 中定义） 中的一个地址给docker0 接口。比如典型的 172.17.42.1 ，掩码为 255.255.0.0 。此后启动的容器内的网口也会自动分配一个同一网段（172.17.0.0/16 ） 的地址。

当创建一个 Docker 容器的时候，同时会创建了一对 veth pair 接口（当数据包发送到一个接口时，另外一个接口也可以收到相同的数据包）。这对接口一端在容器内，即 eth0 ；另一端在本地并被挂载到 docker0 网桥，名称以 veth 开头（ 例如 vethAQI2QT ） 。通过这种方式，主机可以跟容器通信，容器之间也可以相互通信。Docker 就创建了在主机和所有容器之间一个虚拟共享网络。

以上摘自《docker入门到实践》。

docker一般完成网络配置过程如下：

- 1、在主机上创建一对虚拟网卡veth pair设备。veth设备总是成对出现的，它们组成了一个数据的通道，数据从一个设备进入，就会从另一个设备出来。因此，veth设备常用来连接两个网络设备。

- 2、Docker将veth pair设备的一端放在新创建的容器中，并命名为eth0。另一端放在主机中，以veth65f9这样类似的名字命名，并将这个网络设备加入到docker0网桥中，可以通过brctl show命令查看。 

- 3、从docker0子网中分配一个IP给容器使用，并设置docker0的IP地址为容器的默认网关。

## 1.2 docker网络配置简介

两张图开宗明义：

![docker之网络配置](https://i.imgur.com/RvE9QHy.png)

![一张图理解docker网络通信细节](https://i.imgur.com/EAp1Uup.png)

docker通过使用linux桥接提供容器之间的通信，docker0桥接接口的目的就是方便docker管理。当docker daemon启动时，需要做如下操作：

- 1、如果docker0不存在则创建。
- 2、搜索一个与当前路由不冲突的ip段。
- 3、在确定的路由中选择一个ip。
- 4、绑定ip到docker0。

### 1.2.1 docker的四种网络模式

docker run创建docker容器时，可以用`--net`选项指定容器的网络模式，docker有以下4种网络模式：

- 1、host模式，使用`--net=host`指定。
- 2、container模式，使用`--net=container：name or id`指定。
- 3、none模式，使用`--net=none`指定。
- 4、bridge模式，使用`--net=bridge`指定，默认设置。

**1、host模式**

如果启动容器的时候使用 host 模式，那么这个容器将不会获得一个独立的 Network Namespace，而是和宿主机共用一个 Network Namespace。容器将不会虚拟出自己的网卡，配置自己的 IP 等，而是使用宿主机的 IP 和端口。

比如：我们在 10.10.101.105/24 的机器上用 host 模式启动一个含有 web 应用的 Docker 容器，监听 tcp 80 端口。当我们在容器中执行任何类似 ifconfig 命令查看网络环境时，看到的都是宿主机上的信息。而外界访问容器中的应用，则直接使用 10.10.101.105:80 即可，不用任何 NAT 转换，就如直接跑在宿主机中一样。但是，容器的其他方面，如文件系统、进程列表等还是和宿主机隔离的。

**2、container模式**

这个模式下指定新创建的容器和已经存在的容器共享一个 Network Namespace，而不是和宿主机共享。新创建的容器不会创建自己的网卡，配置自己的 IP，而是和一个指定的容器共享 IP、端口范围等。同样，两个容器除了网络方面，其他的如文件系统、进程列表等还是隔离的。两个容器的进程可以通过 lo 网卡设备通信。

**3、none模式**

这个模式和前两个不同。在这种模式下，Docker 容器拥有自己的 Network Namespace，但是，并不为 Docker容器进行任何网络配置。也就是说，这个 Docker 容器没有网卡、IP、路由等信息。需要我们自己为 Docker 容器添加网卡、配置 IP 等。

**4、bridge模式**

![docker桥接模式示意图](https://i.imgur.com/125nF6N.png)

bridge 模式是 Docker 默认的网络设置，此模式会为每一个容器分配 Network Namespace、设置 IP 等，并将一个主机上的 Docker 容器连接到一个虚拟网桥上。当 Docker server 启动时，会在主机上创建一个名为 docker0 的虚拟网桥，此主机上启动的 Docker 容器会连接到这个虚拟网桥上。虚拟网桥的工作方式和物理交换机类似，这样主机上的所有容器就通过交换机连在了一个二层网络中。接下来就要为容器分配 IP 了，Docker 会从 RFC1918 所定义的私有 IP 网段中，选择一个和宿主机不同的IP地址和子网分配给 docker0，连接到 docker0 的容器就从这个子网中选择一个未占用的 IP 使用。如一般 Docker 会使用 172.17.0.0/16 这个网段，并将 172.17.42.1/16 分配给 docker0 网桥（在主机上使用 ifconfig 命令是可以看到 docker0 的，可以认为它是网桥的管理接口，在宿主机上作为一块虚拟网卡使用）。

![osi模型之网桥所在](https://i.imgur.com/CysaGiJ.png)

Linux虚拟网桥的特点:可以设置IP地址和相当于拥有一个隐藏的虚拟网卡。

docker0的地址划分:

- IP:172.17.42.1 子网掩码: 255.255.0.0
- MAC: 02:42:ac:11:00:00 到 02:42:ac:11:ff:ff
- 总共提供65534个地址

docker守护进程在一个容器启动时，实际上它要创建网络连接的两端。一端是在容器中的网络设备，而另一端是在运行docker守护进程的主机上打开一个名为veth*的一个接口，用来实现docker这个网桥与容器的网络通信。

![docker网桥的容器通信示意](https://i.imgur.com/9V0uoaU.png)

需要查看网桥，需要linux的网桥管理程序。

a、列出当前主机网桥

	# brctl工具依赖bridge-utils软件包
	[root@localhost wwwroot]# brctl show						
	bridge name			bridge id				STP enabled			interfaces
	br-e58510118e72		8000.0242a50841cb		no					veth20851a5
																	veth3a6c445
																	veth4517766
																	veth754cbe0
																	vethc04b11d
	docker0				8000.02426826e832		no

我这里两个网桥，因为前期测试docker时跑的。现在docker应用的应该是br-e58510118e72这个。

b、查看当前docker0 ip

	[root@localhost wwwroot]# ifconfig docker0
	docker0: flags=4099<UP,BROADCAST,MULTICAST>  mtu 1500
	        inet 172.17.0.1  netmask 255.255.0.0  broadcast 172.17.255.255
	        inet6 fe80::42:68ff:fe26:e832  prefixlen 64  scopeid 0x20<link>
	        ether 02:42:68:26:e8:32  txqueuelen 0  (Ethernet)
	        RX packets 77211  bytes 7411283 (7.0 MiB)
	        RX errors 0  dropped 0  overruns 0  frame 0
	        TX packets 97645  bytes 146953204 (140.1 MiB)
	        TX errors 0  dropped 0 overruns 0  carrier 0  collisions 0

我这里用这个。

	[root@localhost wwwroot]# ifconfig br-e58510118e72
	br-e58510118e72: flags=4163<UP,BROADCAST,RUNNING,MULTICAST>  mtu 1500
	        inet 172.18.0.1  netmask 255.255.0.0  broadcast 0.0.0.0
	        inet6 fe80::42:a5ff:fe08:41cb  prefixlen 64  scopeid 0x20<link>
	        ether 02:42:a5:08:41:cb  txqueuelen 0  (Ethernet)
	        RX packets 0  bytes 0 (0.0 B)
	        RX errors 0  dropped 0  overruns 0  frame 0
	        TX packets 0  bytes 0 (0.0 B)
	        TX errors 0  dropped 0 overruns 0  carrier 0  collisions 0

在容器运行时，每个容器都会分配一个特定的虚拟机口并桥接到 docker0。每个容器都会配置同 docker0 ip 相同网段的专用 ip 地址，docker0 的 IP 地址被用于所有容器的默认网关。

c、运行一个容器

我已经运行过容器了，直接查看。

	[root@localhost wwwroot]# docker ps 
	CONTAINER ID        IMAGE               COMMAND                  CREATED             STATUS              PORTS                                                                NAMES
	78032ccab6ce        lnmp_php7.1         "docker-php-entryp..."   3 days ago          Up 2 days           0.0.0.0:9000->9000/tcp                                               php7.1
	87c41d6edb03        nginx:1.13          "nginx -g 'daemon ..."   3 days ago          Up 3 days           0.0.0.0:80->80/tcp, 0.0.0.0:1200->1200/tcp, 0.0.0.0:2334->2334/tcp   nginx1.13
	1108c87e77d5        mysql:5.7           "docker-entrypoint..."   6 days ago          Up 3 days           0.0.0.0:3306->3306/tcp                                               mysql5.7
	7a78d89243ec        redis:3.2           "docker-entrypoint..."   6 days ago          Up 3 days           0.0.0.0:6379->6379/tcp                                               redis3.2
	6e92745e8d1d        memcached:1.5       "docker-entrypoint..."   6 days ago          Up 3 days           0.0.0.0:11211->11211/tcp                                             memcached1.5

进去到php7.1的容器。

	[root@localhost wwwroot]# docker exec -it php7.1 bash
	# 未安装网络工具
	root@78032ccab6ce:/www# ifconfig
	bash: ifconfig: command not found
	# 安装工具包
	root@78032ccab6ce:/www# apt-get install -y net-tools
	root@78032ccab6ce:/www# ifconfig
	eth0      Link encap:Ethernet  HWaddr 02:42:ac:12:00:06  
	          inet addr:172.18.0.6  Bcast:0.0.0.0  Mask:255.255.0.0
	          UP BROADCAST RUNNING MULTICAST  MTU:1500  Metric:1
	          RX packets:21402 errors:0 dropped:0 overruns:0 frame:0
	          TX packets:19027 errors:0 dropped:0 overruns:0 carrier:0
	          collisions:0 txqueuelen:0 
	          RX bytes:14162531 (13.5 MiB)  TX bytes:2590738 (2.4 MiB)
	
	lo        Link encap:Local Loopback  
	          inet addr:127.0.0.1  Mask:255.0.0.0
	          UP LOOPBACK RUNNING  MTU:65536  Metric:1
	          RX packets:2 errors:0 dropped:0 overruns:0 frame:0
	          TX packets:2 errors:0 dropped:0 overruns:0 carrier:0
	          collisions:0 txqueuelen:0 
	          RX bytes:138 (138.0 B)  TX bytes:138 (138.0 B)

	root@78032ccab6ce:/www# route -n
	Kernel IP routing table
	Destination     Gateway         Genmask         Flags Metric Ref    Use Iface
	0.0.0.0         172.18.0.1      0.0.0.0         UG    0      0        0 eth0
	172.18.0.0      0.0.0.0         255.255.0.0     U     0      0        0 eth0

docker已经自动创建了eth0的网卡，注意观察ip地址为172.18.0.6和mac地址为02:42:ac:12:00:06。

对应我们的网桥列表中`br-e58510118e72`这个网桥的`bridge id`为8000.0242a50841cb（好像看不出具体对应那个接口，这里待补充）。

列出查看当前的网络详细信息，做参考。

	[root@localhost wwwroot]# ifconfig
	br-e58510118e72: flags=4163<UP,BROADCAST,RUNNING,MULTICAST>  mtu 1500
	        inet 172.18.0.1  netmask 255.255.0.0  broadcast 0.0.0.0
	        inet6 fe80::42:a5ff:fe08:41cb  prefixlen 64  scopeid 0x20<link>
	        ether 02:42:a5:08:41:cb  txqueuelen 0  (Ethernet)
	        RX packets 2179  bytes 244717 (238.9 KiB)
	        RX errors 0  dropped 0  overruns 0  frame 0
	        TX packets 2450  bytes 367035 (358.4 KiB)
	        TX errors 0  dropped 0 overruns 0  carrier 0  collisions 0
	
	docker0: flags=4099<UP,BROADCAST,MULTICAST>  mtu 1500
	        inet 172.17.0.1  netmask 255.255.0.0  broadcast 172.17.255.255
	        inet6 fe80::42:68ff:fe26:e832  prefixlen 64  scopeid 0x20<link>
	        ether 02:42:68:26:e8:32  txqueuelen 0  (Ethernet)
	        RX packets 77211  bytes 7411283 (7.0 MiB)
	        RX errors 0  dropped 0  overruns 0  frame 0
	        TX packets 97645  bytes 146953204 (140.1 MiB)
	        TX errors 0  dropped 0 overruns 0  carrier 0  collisions 0
	
	eno16777984: flags=4163<UP,BROADCAST,RUNNING,MULTICAST>  mtu 1500
	        inet 192.168.8.234  netmask 255.255.255.0  broadcast 192.168.8.255
	        inet6 fe80::20c:29ff:fedb:d7ec  prefixlen 64  scopeid 0x20<link>
	        ether 00:0c:29:db:d7:ec  txqueuelen 1000  (Ethernet)
	        RX packets 138519263  bytes 11725473088 (10.9 GiB)
	        RX errors 0  dropped 62  overruns 0  frame 0
	        TX packets 132204961  bytes 7687001119 (7.1 GiB)
	        TX errors 0  dropped 0 overruns 0  carrier 0  collisions 0
	
	lo: flags=73<UP,LOOPBACK,RUNNING>  mtu 65536
	        inet 127.0.0.1  netmask 255.0.0.0
	        inet6 ::1  prefixlen 128  scopeid 0x10<host>
	        loop  txqueuelen 0  (Local Loopback)
	        RX packets 9802  bytes 63063666 (60.1 MiB)
	        RX errors 0  dropped 0  overruns 0  frame 0
	        TX packets 9802  bytes 63063666 (60.1 MiB)
	        TX errors 0  dropped 0 overruns 0  carrier 0  collisions 0
	
	veth4517766: flags=4163<UP,BROADCAST,RUNNING,MULTICAST>  mtu 1500
	        inet6 fe80::9811:fdff:feac:90e2  prefixlen 64  scopeid 0x20<link>
	        ether 9a:11:fd:ac:90:e2  txqueuelen 0  (Ethernet)
	        RX packets 678  bytes 213173 (208.1 KiB)
	        RX errors 0  dropped 0  overruns 0  frame 0
	        TX packets 837  bytes 193940 (189.3 KiB)
	        TX errors 0  dropped 0 overruns 0  carrier 0  collisions 0
	
	veth20851a5: flags=4163<UP,BROADCAST,RUNNING,MULTICAST>  mtu 1500
	        inet6 fe80::28e8:dbff:fefa:53dd  prefixlen 64  scopeid 0x20<link>
	        ether 2a:e8:db:fa:53:dd  txqueuelen 0  (Ethernet)
	        RX packets 19027  bytes 2590738 (2.4 MiB)
	        RX errors 0  dropped 0  overruns 0  frame 0
	        TX packets 21402  bytes 14162531 (13.5 MiB)
	        TX errors 0  dropped 0 overruns 0  carrier 0  collisions 0
	
	veth3a6c445: flags=4163<UP,BROADCAST,RUNNING,MULTICAST>  mtu 1500
	        inet6 fe80::9ca3:4eff:feb8:2eed  prefixlen 64  scopeid 0x20<link>
	        ether 9e:a3:4e:b8:2e:ed  txqueuelen 0  (Ethernet)
	        RX packets 3101  bytes 3877321 (3.6 MiB)
	        RX errors 0  dropped 0  overruns 0  frame 0
	        TX packets 3438  bytes 1711375 (1.6 MiB)
	        TX errors 0  dropped 0 overruns 0  carrier 0  collisions 0
	
	veth754cbe0: flags=4163<UP,BROADCAST,RUNNING,MULTICAST>  mtu 1500
	        inet6 fe80::dc02:3aff:feec:628b  prefixlen 64  scopeid 0x20<link>
	        ether de:02:3a:ec:62:8b  txqueuelen 0  (Ethernet)
	        RX packets 2179  bytes 244717 (238.9 KiB)
	        RX errors 0  dropped 0  overruns 0  frame 0
	        TX packets 2450  bytes 367035 (358.4 KiB)
	        TX errors 0  dropped 0 overruns 0  carrier 0  collisions 0
	
	vethc04b11d: flags=4163<UP,BROADCAST,RUNNING,MULTICAST>  mtu 1500
	        inet6 fe80::4424:62ff:fe28:c1b1  prefixlen 64  scopeid 0x20<link>
	        ether 46:24:62:28:c1:b1  txqueuelen 0  (Ethernet)
	        RX packets 0  bytes 0 (0.0 B)
	        RX errors 0  dropped 0  overruns 0  frame 0
	        TX packets 50  bytes 3132 (3.0 KiB)
	        TX errors 0  dropped 0 overruns 0  carrier 0  collisions 0

上面可以看出 br-e58510118e72 扮演着几个容器的虚拟接口veth* interface 桥接的角色。

### 1.2.2 容器使用特定范围的ip

Docker 会尝试寻找没有被主机使用的 ip 段，尽管它适用于大多数情况下，但是它不是万能的，有时候我们还是需要对ip进一步规划。

Docker允许你管理docker0桥接或者通过-b选项自定义桥接网卡，需要安装bridge-utils软件包。

基本步骤如下：

- 1、确保docker的进程是停止的。
- 2、创建自定义网桥。
- 3、给网桥分配特定的ip。
- 4、以-b的方式指定网桥。

下面这段代码未尝试，供借鉴。

	[root@localhost wwwroot]# service docker stop
	[root@localhost wwwroot]# ip link set dev docker0 down
	[root@localhost wwwroot]# brctl delbr docker0
	[root@localhost wwwroot]# brctl addbr bridge0
	[root@localhost wwwroot]# ip addr add 192.168.5.1/24 dev bridge0
	[root@localhost wwwroot]# ip link set dev bridge0 up
	[root@localhost wwwroot]# ip addr show bridge0
	[root@localhost wwwroot]# 'DOCKER_OPTS="-b=bridge0"' >> /etc/default/docker
	[root@localhost wwwroot]# service docker start

### 1.2.3 不同主机之间容器的通信

以上的容器通信都是基于单机的容器使用，那么不同主机的容器之间的通信可以借助于 pipework 这个工具。

1、安装pipework

	[root@localhost wwwroot]# git clone https://github.com/jpetazzo/pipework.git

报错的话就更新下软件包。

	yum update nss curl libcurl  # nss为名称解析和认证服务 curl为网络请求库

添加到用户指令。

	[root@localhost wwwroot]# cp -rp pipework/pipework /usr/local/bin/
	[root@localhost wwwroot]# pipework
	Syntax:
	pipework <hostinterface> [-i containerinterface] [-l localinterfacename] [-a addressfamily] <guest> <ipaddr>/<subnet>[@default_gateway] [macaddr][@vlan]
	pipework <hostinterface> [-i containerinterface] [-l localinterfacename] <guest> dhcp [macaddr][@vlan]
	pipework route <guest> <route_command>
	pipework rule <guest> <rule_command>
	pipework tc <guest> <tc_command>
	pipework --wait [-i containerinterface]

2、pipework指定容器ip

如果删除了默认的 docker0 桥接，把 docker 默认桥接指定到了 br0，则最好在创建容器的时候加上`--net=none`，防止自动分配的 IP 在局域网中有冲突。

	[root@localhost wwwroot]# pipework br0 -i eth0 a46657528059 192.168.115.10/24@192.168.115.2
	# 默认不指定网卡设备名，则默认添加为 eth1
	# 另外 pipework 不能添加静态路由，如果有需求则可以在 run 的时候加上 --privileged=true 权限在容器中手动添加，
	# 但这种安全性有缺陷，可以通过 ip netns 操作

使用ip netns添加静态路由，避免创建容器使用--privileged=true选项造成一些不必要的安全问题：

	[root@localhost wwwroot]# docker inspect --format="{{ .State.Pid }}" a46657528059 # 获取指定容器 pid
	[root@localhost wwwroot]# ln -s /proc/6350/ns/net /var/run/netns/6350
	[root@localhost wwwroot]# ip netns exec 6350 ip route add 192.168.0.0/16 dev eth0 via 192.168.115.2
	[root@localhost wwwroot]# ip netns exec 6350 ip route    # 添加成功
	[root@localhost wwwroot]# 192.168.0.0/16 via 192.168.115.2 dev eth0

这节未测试，之后自己测试爬坑再修改。

[pipework官档](https://github.com/jpetazzo/pipework)

[docker网络详解及pipework源码解读与实践](http://www.infoq.com/cn/articles/docker-network-and-pipework-open-source-explanation-practice)

[此篇博文原出处：docker基础介绍](http://www.cnblogs.com/kevingrace/p/6547616.html)

# 2、docker的通信设置工具（Pipework和Open vSwitch）

## 2.1 再次细说docker的四种网络模式

一张图开宗明义。

![docker容器通信模式对比](https://i.imgur.com/BhXPgQw.png)

南北向通信指容器与宿主机外界的访问机制，东西向流量指同一宿主机上，与其他容器相互访问的机制。

### 2.1.1 host模式

由于容器和宿主机共享同一个网络命名空间，换言之，容器的IP地址即为宿主机的IP地址。所以容器可以和宿主机一样，使用宿主机的任意网卡，实现和外界的通信。

![容器通信host模式示意](https://i.imgur.com/1045dMj.jpg)

采用host模式的容器，可以直接使用宿主机的IP地址与外界进行通信，若宿主机具有公有IP，那么容器也拥有这个公有IP。同时容器内服务的端口也可以使用宿主机的端口，

无需额外进行NAT转换，而且由于容器通信时，不再需要通过linux bridge等方式转发或者数据包的拆封，性能上有很大优势。

当然，这种模式有优势，也就有劣势，主要包括以下几个方面：

- 最明显的就是容器不再拥有隔离、独立的网络栈。容器会与宿主机竞争网络栈的使用，并且容器的崩溃就可能导致宿主机崩溃，在生产环境中，这种问题可能是不被允许的。
- 容器内部将不再拥有所有的端口资源，因为一些端口已经被宿主机服务、bridge模式的容器端口绑定等其他服务占用掉了。

### 2.1.2 bridge模式

bridge模式是docker默认的，也是开发者最常使用的网络模式。在这种模式下，docker为容器创建独立的网络栈，保证容器内的进程使用独立的网络环境，实现容器之间、容器与宿主机之间的网络栈隔离。同时，通过宿主机上的docker0网桥，容器可以与宿主机乃至外界进行网络通信。

![容器通信bridge模式示意](https://i.imgur.com/H1sXwp8.jpg)

从上面的网络模型可以看出，容器从原理上是可以与宿主机乃至外界的其他机器通信的。同一宿主机上，容器之间都是连接掉docker0这个网桥上的，它可以作为虚拟交换机使容器可以相互通信。

然而，由于宿主机的IP地址与容器veth pair的 IP地址均不在同一个网段，故仅仅依靠veth pair和namespace的技术，还不足以使宿主机以外的网络主动发现容器的存在。为了使外界可以访问容器中的进程，docker采用了端口绑定的方式，也就是通过iptables的NAT，将宿主机上的端口端口流量转发到容器内的端口上。
 
举一个简单的例子，使用下面的命令创建容器，并将宿主机的3306端口绑定到容器的3306端口：

	docker run -tid --name db -p 3306:3306 MySQL
 
在宿主机上，可以通过iptables -t nat -L -n，查到一条DNAT规则：
 
	DNAT tcp -- 0.0.0.0/0 0.0.0.0/0 tcp dpt:3306 to:172.17.0.5:3306
 
上面的172.17.0.5即为bridge模式下，创建的容器IP。
 
很明显，bridge模式的容器与外界通信时，必定会占用宿主机上的端口，从而与宿主机竞争端口资源，对宿主机端口的管理会是一个比较大的问题。同时，由于容器与外界通信是基于三层上iptables NAT，性能和效率上的损耗是可以预见的。

### 2.1.3 none模式

在这种模式下，容器有独立的网络栈，但不包含任何网络配置，只具有lo这个loopback网卡用于进程通信。也就是说，none模式为容器做了最少的网络设置。

但是俗话说得好“少即是多”，在没有网络配置的情况下，通过第三方工具或者手工的方式，开发这任意定制容器的网络，提供了最高的灵活性。

### 2.1.4 container模式

其他网络模式是docker中一种较为特别的网络的模式。在这个模式下的容器，会使用其他容器的网络命名空间，其网络隔离性会处于bridge桥接模式与host模式之间。

当容器共享其他容器的网络命名空间，则在这两个容器之间不存在网络隔离，而它们又与宿主机以及除此之外其他的容器存在网络隔离。

![容器通信container模式](https://i.imgur.com/Ug44NON.jpg)

### 2.1.5 用户自定义网络模式（参考）

在用户定义网络模式下，开发者可以使用任何docker支持的第三方网络driver来定制容器的网络。并且，docker 1.9以上的版本默认自带了bridge和overlay两种类型的自定义网络driver。可以用于集成calico、weave、openvswitch等第三方厂商的网络实现。

除了docker自带的bridge driver，其他的几种driver都可以实现容器的跨主机通信。而基于bdrige driver的网络，docker会自动为其创建iptables规则，保证与其他网络之间、与docker0之间的网络隔离。

例如，使用下面的命令创建一个基于bridge driver的自定义网络：
 
	docker network create bri1
 
则docker会自动生成如下的iptables规则，保证不同网络上的容器无法互相通信。
 
	 -A DOCKER-ISOLATION -i br-8dba6df70456 -o docker0 -j DROP
	 -A DOCKER-ISOLATION -i docker0 -o br-8dba6df70456 -j DROP
 
除此之外，bridge driver的所有行为都和默认的bridge模式完全一致。而overlay及其他driver，则可以实现容器的跨主机通信。

## 2.2 docker容器之间的通信（待实践）

### 2.2.1 同主机容器通信

早期大家的跨主机通信方案主要有以下几种：

- 1、容器使用host模式：容器直接使用宿主机的网络，这样天生就可以支持跨主机通信。虽然可以解决跨主机通信问题，但这种方式应用场景很有限，容易出现端口冲突，也无法做到隔离网络环境，一个容器崩溃很可能引起整个宿主机的崩溃。
- 2、端口绑定：通过绑定容器端口到宿主机端口，跨主机通信时，使用主机IP+端口的方式访问容器中的服务。显而易见，这种方式仅能支持网络栈的四层及以上的应用，并且容器与宿主机紧耦合，很难灵活的处理，可扩展性不佳。
- 3、docker外定制容器网络：在容器通过docker创建完成后，然后再通过修改容器的网络命名空间来定义容器网络。典型的就是很久以前的pipework，容器以none模式创建，pipework通过进入容器的网络命名空间为容器重新配置网络，这样容器网络可以是静态IP、vxlan网络等各种方式，非常灵活，容器启动的一段时间内会没有IP，明显无法在大规模场景下使用，只能在实验室中测试使用。
- 4、第三方SDN定义容器网络：使用Open vSwitch或Flannel等第三方SDN工具，为容器构建可以跨主机通信的网络环境。这些方案一般要求各个主机上的docker0网桥的cidr不同，以避免出现IP冲突的问题，限制了容器在宿主机上的可获取IP范围。并且在容器需要对集群外提供服务时，需要比较复杂的配置，对部署实施人员的网络技能要求比较高。

上面这些方案有各种各样的缺陷，同时也因为跨主机通信的迫切需求，docker 1.9版本时，官方提出了基于vxlan的overlay网络实现，原生支持容器的跨主机通信。同时，还支持通过libnetwork的
plugin机制扩展各种第三方实现，从而以不同的方式实现跨主机通信。就目前社区比较流行的方案来说，跨主机通信的基本实现方案有以下几种：

- 1、基于隧道的overlay网络：按隧道类型来说，不同的公司或者组织有不同的实现方案。docker原生的overlay网络就是基于vxlan隧道实现的。ovn则需要通过geneve或者stt隧道来实现的。flannel
最新版本也开始默认基于vxlan实现overlay网络。
- 2、基于包封装的overlay网络：基于UDP封装等数据包包装方式，在docker集群上实现跨主机网络。典型实现方案有weave、flannel的早期版本。
- 3、基于三层实现SDN网络：基于三层协议和路由，直接在三层上实现跨主机网络，并且通过iptables实现网络的安全隔离。典型的方案为Project Calico。同时对不支持三层路由的环境，Project Calico还提供了基于IPIP封装的跨主机网络实现。

**1、创建主机使用特定范围内的ip**

Docker 会尝试寻找没有被主机使用的ip段，尽管它适用于大多数情况下，但是它不是万能的，有时候我们还是需要对ip进一步规划。
Docker允许你管理docker0桥接或者通过-b选项自定义桥接网卡，需要安装bridge-utils软件包。操作流程如下：

- 1、确保docker的进程是停止的。
- 2、创建自定义网桥。
- 3、给网桥分配特定的ip。
- 4、以-b的方式指定网桥。
	  
具体操作过程如下（比如创建容器的时候，指定ip为192.168.5.1/24网段的）：

	[root@localhost ~]# service docker stop
	[root@localhost ~]# ip link set dev docker0 down
	[root@localhost ~]# brctl delbr docker0
	[root@localhost ~]# brctl addbr bridge0
	[root@localhost ~]# ip addr add 192.168.5.1/24 dev bridge0      //注意，这个192.168.5.1就是所建容器的网关地址。通过docker inspect container_id能查看到
	[root@localhost ~]# ip link set dev bridge0 up
	[root@localhost ~]# ip addr show bridge0
	[root@localhost ~]# vim /etc/sysconfig/docker      //即将虚拟的桥接口由默认的docker0改为bridge0
	将
	OPTIONS='--selinux-enabled --log-driver=journald'
	改为
	OPTIONS='--selinux-enabled --log-driver=journald -b=bridge0'    //即添加-b=bridge0
	  
	[root@localhost ~]# service docker restart

可以利用pipework为容器指定一个固定的ip，操作方法非常简单，如下：

	[root@node1 ~]# brctl addbr br0
	[root@node1 ~]# ip link set dev br0 up
	[root@node1 ~]# ip addr add 192.168.114.1/24 dev br0                        //这个ip相当于br0网桥的网关ip，可以随意设定。
	[root@node1 ~]# docker run -ti -d --net=none --name=my-test1 docker.io/nginx /bin/bash
	[root@node1 ~]# pipework br0 -i eth0 my-test1 192.168.114.100/24@192.168.114.1
	 
	[root@node1 ~]# docker exec -ti my-test1 /bin/bash
	root@cf370a090f63:/# ip addr
	1: lo: <LOOPBACK,UP,LOWER_UP> mtu 65536 qdisc noqueue state UNKNOWN group default
	    link/loopback 00:00:00:00:00:00 brd 00:00:00:00:00:00
	    inet 127.0.0.1/8 scope host lo
	       valid_lft forever preferred_lft forever
	    inet6 ::1/128 scope host
	       valid_lft forever preferred_lft forever
	57: eth0@if58: <BROADCAST,MULTICAST,UP,LOWER_UP> mtu 1500 qdisc pfifo_fast state UP group default qlen 1000
	    link/ether b2:c1:8d:92:33:e2 brd ff:ff:ff:ff:ff:ff link-netnsid 0
	    inet 192.168.114.100/24 brd 192.168.114.255 scope global eth0
	       valid_lft forever preferred_lft forever
	    inet6 fe80::b0c1:8dff:fe92:33e2/64 scope link
	       valid_lft forever preferred_lft forever
	 
	 
	再启动一个容器
	[root@node1 ~]# docker run -ti -d --net=none --name=my-test2 docker.io/nginx /bin/bash
	[root@node1 ~]# pipework br0 -i eth0 my-test12 192.168.114.200/24@192.168.114.1
	[root@node1 ~]# pipework br0 -i eth0 my-test2 192.168.114.200/24@192.168.114.1
 
这样，my-test1容器和my-test2容器在同一个宿主机上，所以它们固定后的ip是可以相互ping通的，如果是在不同的宿主机上，则就无法ping通！
 
所以说：这样使用pipework指定固定ip的容器，在同一个宿主机下的容器间的ip是可以相互ping通的，但是跨主机的容器通过这种方式固定ip后就不能ping通了。


### 2.2.2 跨主机容器通信1

pipework等网络工具的安装之前有介绍，此处略去。
     
查看Docker宿主机上的桥接网络

	# brctl工具依赖bridge-utils软件包
	[root@localhost wwwroot]# brctl show						
	bridge name			bridge id				STP enabled			interfaces
	docker0				8000.0242a50841cb		no					veth20851a5
																	veth3a6c445
																	veth4517766
																	veth754cbe0
																	vethc04b11d
    
有两种方式做法：

- 1、可以选择删除docker0，直接把docker的桥接指定为br0；
- 2、可以选择保留使用默认docker0的配置，这样单主机容器之间的通信可以通过docker0；跨主机不同容器之间通过pipework将容器的网卡桥接到br0上，这样跨主机容器之间就可以通信了。
    
如果保留了docker0，则容器启动时不加`--net=none`参数，那么本机容器启动后就是默认的docker0自动分配的ip（默认是172.17.1.0/24网段），它们之间是可以通信的；

跨宿主机的容器创建时要加`--net=none`参数，待容器启动后通过pipework给容器指定ip，这样跨宿主机的容器ip是在同一网段内的同网段地址，因此可以通信。
   
一般来说：最好在创建容器的时候加上`--net=none`，防止自动分配的IP在局域网中有冲突。若是容器创建后自动获取ip，下次容器启动会ip有变化，可能会和物理网段中的ip冲突。

下面实例说明，我未操作，这里先借鉴。
  
	宿主机信息
	ip：192.168.1.23          （网卡设备为eth0）
	gateway：192.168.1.1
	netmask：255.255.255.0
 
**1、删除虚拟桥接卡docker0的配置**

	[root@localhost ~]# service docker stop
	[root@localhost ~]# ip link set dev docker0 down
	[root@localhost ~]# brctl delbr docker0
	[root@localhost ~]# brctl addbr br0
	[root@localhost ~]# ip link set dev br0 up
	# 删除宿主机网卡的IP（如果是使用这个地址进行的远程连接，这一步操作后就会断掉；如果是使用外网地址连接的话，就不会断开）      
	[root@localhost ~]# ip addr del 192.168.1.23/24 dev eth0
	# 将宿主主机的ip设置到br0       
	[root@localhost ~]# ip addr add 192.168.1.23/24 dev br0 
	# 将宿主机网卡挂到br0上       
	[root@localhost ~]# brctl addif br0 eth0 
	# 删除默认的原路由，其实就是eth0上使用的原路由192.168.1.1（这步小心，注意删除后要保证机器能远程连接上，最好是通过外网ip远程连的。别删除路由后，远程连接不上，中断了）                       
	[root@localhost ~]# ip route del default       
	# 为br0设置路由                
	[root@localhost ~]# ip route add default via 192.168.1.1 dev br0  
 	# 即将虚拟的桥接口由默认的docker0改为bridge0，即添加-b=br0
	[root@localhost ~]# vim /etc/sysconfig/docker                 
	将
	OPTIONS='--selinux-enabled --log-driver=journald'
	改为
	OPTIONS='--selinux-enabled --log-driver=journald -b=br0'    
	[root@localhost ~]# service docker start
  
  
启动一个手动设置网络的容器

	[root@localhost ~]# docker ps
	CONTAINER ID        IMAGE               COMMAND             CREATED             STATUS              PORTS               NAMES
	6e64eade06d1        docker.io/centos    "/bin/bash"         10 seconds ago      Up 9 seconds                            my-centos
	[root@localhost ~]# docker run -itd --net=none --name=my-test1 docker.io/centos
   
为my-test1容器设置一个与桥接物理网络同地址段的ip（如下，"ip@gateway"）。默认不指定网卡设备名，则默认添加为eth0。可以通过-i参数添加网卡设备名。

	[root@localhost ~]# pipework br0 -i eth0 my-test1 192.168.1.190/24@192.168.1.1
  
同理，在其他机器上启动容器，并类似上面用pipework设置一个同网段类的ip，这样跨主机的容器就可以相互ping通了！
  
**2、保留默认虚拟桥接卡docker0的配置**

	[root@localhost ~]# cd /etc/sysconfig/network-scripts/
	[root@localhost network-scripts]# cp ifcfg-eth0 ifcfg-eth0.bak
	[root@localhost network-scripts]# cp ifcfg-eth0 ifcfg-br0
	# 增加BRIDGE=br0，删除IPADDR,NETMASK,GATEWAY,DNS的设置
	[root@localhost network-scripts]# vim ifcfg-eth0            
	......
	BRIDGE=br0
	# 修改DEVICE为br0,Type为Bridge,把eth0的网络设置设置到这里来（里面应该有ip，网关，子网掩码或DNS设置）
	[root@localhost network-scripts]# vim ifcfg-br0 
	......
	TYPE=Bridge
	DEVICE=br0
     
	[root@localhost network-scripts]# service network restart    
	[root@localhost network-scripts]# service docker restart
     
开启一个容器并指定网络模式为none（这样，创建的容器就不会通过docker0自动分配ip了，而是根据pipework工具自定ip指定）。

	[root@localhost network-scripts]# docker images
	REPOSITORY          TAG                 IMAGE ID            CREATED             SIZE
	docker.io/centos    latest              67591570dd29        3 months ago        191.8 MB
	[root@localhost network-scripts]# docker run -itd --net=none --name=my-centos docker.io/centos /bin/bash
	6e64eade06d1eb20be3bd22ece2f79174cd033b59182933f7bbbb502bef9cb0f
  
接着给容器配置网络。

	[root@localhost network-scripts]# pipework br0 -i eth0 my-centos 192.168.1.150/24@192.168.1.1
	[root@localhost network-scripts]# docker attach 6e64eade06d1
	[root@6e64eade06d1 /]# ifconfig eth0                 //若没有ifconfig命令，可以yum安装net-tools工具
	eth0      Link encap:Ethernet  HWaddr 86:b6:6b:e8:2e:4d
	          inet addr:192.168.1.150  Bcast:0.0.0.0  Mask:255.255.255.0
	          inet6 addr: fe80::84b6:6bff:fee8:2e4d/64 Scope:Link
	          UP BROADCAST RUNNING MULTICAST  MTU:1500  Metric:1
	          RX packets:8 errors:0 dropped:0 overruns:0 frame:0
	          TX packets:9 errors:0 dropped:0 overruns:0 carrier:0
	          collisions:0 txqueuelen:1000
	          RX bytes:648 (648.0 B)  TX bytes:690 (690.0 B)
 
	[root@6e64eade06d1 /]# route -n
	Kernel IP routing table
	Destination     Gateway         Genmask         Flags Metric Ref    Use Iface
	0.0.0.0         192.168.1.1     0.0.0.0         UG    0      0        0 eth0
	192.168.115.0   0.0.0.0         255.255.255.0   U     0      0        0 eth0
     
另外pipework不能添加静态路由，如果有需求则可以在run的时候加上--privileged=true 权限在容器中手动添加，但这种方法安全性有缺陷。
除此之外，可以通过ip netns（--help参考帮助）添加静态路由，以避免创建容器使用--privileged=true选项造成一些不必要的安全问题：
     
如下获取指定容器的pid。

	[root@localhost network-scripts]# docker inspect --format="{{ .State.Pid }}" 6e64eade06d1
	7852
	[root@localhost network-scripts]# ln -s /proc/7852/ns/net /var/run/netns/7852
	[root@localhost network-scripts]# ip netns exec 7852 ip route add 192.168.0.0/16 dev eth0 via 192.168.1.1
	[root@localhost network-scripts]# ip netns exec 7852 ip route
	192.168.0.0/16 via 192.168.1.1 dev eth0
     
同理，在其它宿主机进行相应的配置，新建容器并使用pipework添加虚拟网卡桥接到br0，如此创建的容器间就可以相互通信了。

### 2.2.3 跨主机容器通信2

除了上面使用的pipework工具还，还可以使用虚拟交换机(Open vSwitch)进行docker容器间的网络通信，废话不多说，下面说下Open vSwitch的使用。

**1、在slave1和2上面分别安装open vswitch**

	[root@Slave1 ~]# # yum -y install wget openssl-devel kernel-devel
	[root@Slave1 ~]# yum groupinstall "Development Tools"
	[root@Slave1 ~]# adduser ovswitch
	[root@Slave1 ~]# su - ovswitch
	[root@Slave1 ~]$ wget http://openvswitch.org/releases/openvswitch-2.3.0.tar.gz
	[root@Slave1 ~]$ tar -zxvpf openvswitch-2.3.0.tar.gz
	[root@Slave1 ~]$ mkdir -p ~/rpmbuild/SOURCES
	[root@Slave1 ~]$ sed 's/openvswitch-kmod, //g' openvswitch-2.3.0/rhel/openvswitch.spec > openvswitch-2.3.0/rhel/openvswitch_no_kmod.spec
	[root@Slave1 ~]$ cp openvswitch-2.3.0.tar.gz rpmbuild/SOURCES/
	    
	[root@Slave1 ~]$ rpmbuild -bb --without check ~/openvswitch-2.3.0/rhel/openvswitch_no_kmod.spec
	    
	[root@Slave1 ~]$ exit
	    
	[root@Slave1 ~]# yum localinstall /home/ovswitch/rpmbuild/RPMS/x86_64/openvswitch-2.3.0-1.x86_64.rpm
	[root@Slave1 ~]# mkdir /etc/openvswitch
	[root@Slave1 ~]# setenforce 0
	[root@Slave1 ~]# systemctl start openvswitch.service
	[root@Slave1 ~]# systemctl  status openvswitch.service -l

**2、在slave1和2上建立OVS Bridge并配置路由**

	# 在slave1宿主机上设置docker容器内网ip网段172.17.1.0/24
	[root@Slave1 ~]# vim /proc/sys/net/ipv4/ip_forward
	1
	[root@Slave1 ~]# ovs-vsctl add-br obr0
	[root@Slave1 ~]# ovs-vsctl add-port obr0 gre0 -- set Interface gre0 type=gre options:remote_ip=192.168.115.5
	    
	[root@Slave1 ~]# brctl addbr kbr0
	[root@Slave1 ~]# brctl addif kbr0 obr0
	[root@Slave1 ~]# ip link set dev docker0 down
	[root@Slave1 ~]# ip link del dev docker0
	    
	[root@Slave1 ~]# vim /etc/sysconfig/network-scripts/ifcfg-kbr0
	ONBOOT=yes
	BOOTPROTO=static
	IPADDR=172.17.1.1
	NETMASK=255.255.255.0
	GATEWAY=172.17.1.0
	USERCTL=no
	TYPE=Bridge
	IPV6INIT=no
	    
	[root@Slave1 ~]# vim /etc/sysconfig/network-scripts/route-ens32
	172.17.2.0/24 via 192.168.115.6 dev ens32
	  
	[root@Slave1 ~]# systemctl  restart network.service


	# 在slave2宿主机上设置docker容器内网ip网段172.17.2.0/24
	[root@Slave2 ~]# vim /proc/sys/net/ipv4/ip_forward
	1
	[root@Slave2 ~]# ovs-vsctl add-br obr0
	[root@Slave2 ~]# ovs-vsctl add-port obr0 gre0 -- set Interface gre0 type=gre options:remote_ip=192.168.115.6
	   
	[root@Slave2 ~]# brctl addbr kbr0
	[root@Slave2 ~]# brctl addif kbr0 obr0
	[root@Slave2 ~]# ip link set dev docker0 down
	[root@Slave2 ~]# ip link del dev docker0
	   
	[root@Slave2 ~] vim /etc/sysconfig/network-scripts/ifcfg-kbr0
	ONBOOT=yes
	BOOTPROTO=static
	IPADDR=172.17.2.1
	NETMASK=255.255.255.0
	GATEWAY=172.17.2.0
	USERCTL=no
	TYPE=Bridge
	IPV6INIT=no
	   
	[root@Slave2 ~]# vim /etc/sysconfig/network-scripts/route-ens32
	172.17.1.0/24 via 192.168.115.5 dev ens32
	   
	[root@Slave2 ~]# systemctl  restart network.service

**3、启动容器测试**

slave1和2上修改docker启动的虚拟网卡绑定为kbr0，重启docker进程
 
a、在Server1宿主机上启动容器,然具体操作过程如下后登陆容器内查看ip，就会发现ip是上面设定额172.17.1.0/24网段的。

	[root@Slave1 ~]# docker run -idt --name my-server1 daocloud.io/library/centos/bin/bash
 
 
b、在Server2宿主机上启动容器，然后登陆容器内查看ip，就会发现ip是上面设定额172.17.2.0/24网段的。

	[root@Slave2 ~]#docker run -idt --name my-server1 daocloud.io/library/centos /bin/bash
 
然后在上面启动的容内互ping对方容器，发现是可以ping通的。

## 2.3 工具介绍

### 2.3.1 ip 命令

ip 是 iproute2 工具包里面的一个命令行工具，用于配置网络接口以及路由表。

iproute2 正在逐步取代旧的 net-tools（ifconfig），所以是时候学习下 iproute2 的使用方法啦。

[参考地址：ip 命令用法归纳](https://www.jianshu.com/p/cea2b30564c1)

### 2.3.2 pipework

pipework是Docker公司工程师Jerome Petazzoni在Github上发布的名为pipework的工具。号称是容器网络的SDN解决方案，可以在复杂的场景下将容器连接起来。它既支持普通的LXC容器，也支持Docker容器。

**1、需求场景**

在使用Docker的过程中，有时候我们会有将Docker容器配置到和主机同一网段的需求。要实现这个需求，我们只要将Docker容器和主机的网卡桥接起来，再给Docker容器配上IP就可以了。

**2、安装pipework**

之前 1.2.3 小节已作介绍。

**3、使用pipework**

	[root@localhost lnmp]# pipework
	Syntax:
	pipework <hostinterface> [-i containerinterface] [-l localinterfacename] [-a addressfamily] <guest> <ipaddr>/<subnet>[@default_gateway] [macaddr][@vlan]
	pipework <hostinterface> [-i containerinterface] [-l localinterfacename] <guest> dhcp [macaddr][@vlan]
	pipework route <guest> <route_command>
	pipework rule <guest> <rule_command>
	pipework tc <guest> <tc_command>
	pipework --wait [-i containerinterface]

[参考地址：pipework使用介绍](http://blog.csdn.net/happyAnger6/article/details/68972682)

