# swork

#### 介绍
Swoole微型开发框架：PHP注解优雅的方式实现全站内存驻留的方式，运行高效，稳定！

#### 软件架构
软件架构说明


#### 安装教程

1. 安装php
```
// MacOSX
1) 如果没有安装xcode，请先安装并打开xcode，等待xcode完成初始化配置并重启电脑
2) 安装homebrew
https://brew.sh/index_zh-cn
3) 安装php7
brew install php@7.2
brew link php@7.2
4) 安装拓展
pecl install mbstring
pecl install gd
pecl install pdo
pecl install mysqlnd
pecl install bcmath
pecl install redis

// CentOS7
1) 增加php7源
yum install epel-release
rpm -Uvh https://mirror.webtatic.com/yum/el7/webtatic-release.rpm
2) 安装php7和基础依赖拓展
yum install php72w
yum install php72w-cli 
yum install php72w-common 
yum install php72w-devel 
yum install php72w-embedded
yum install php72w-pear
3) 安装拓展（同MaxOSX）

// windows
建议安装虚拟机或者ubuntu子系统，用linux的安装方式安装
```

2. 安装hiredis
```
1）检查环境依赖
gcc-4.8 或更高版本
make
autoconf
pcre (CentOS系统可以执行命令：yum install pcre-devel)
2) 下载hiredis并解压
https://github.com/redis/hiredis/archive/v0.14.0.tar.gz
3) 编译安装hiredis
cd hiredis
make -j
sudo make install
sudo ldconfig
4）在用户配置文件中写入hiredis路径
vi ~/.bash_profile
在最后一行添加 export LD_LIBRARY_PATH=$LD_LIBRARY_PATH:/usr/local/lib
source ~/.bash_profile
```

3. 安装swoole拓展
```
1) 下载swoole4.0.1源码并解压
https://github.com/swoole/swoole-src/archive/v4.0.1.tar.gz
2) 编译安装swoole
cd swoole
phpize (ubuntu 没有安装phpize，可执行命令：sudo apt-get install php-dev来安装phpize)
./configure --enable-async-redis
make 
sudo make install
3) 根据安装成功的提示在php.ini文件增加拓展声明
```

#### 使用说明
```
1. 如有需要，可在xxx/config/server.php中修改应用端口号
2. 执行 php xxx/bin/swork 命令运行应用
```