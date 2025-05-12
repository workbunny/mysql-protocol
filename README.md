<p align="center"><img width="260px" src="https://chaz6chez.cn/images/workbunny-logo.png" alt="workbunny"></p>

**<p align="center">workbunny/mysql-protocol</p>**

**<p align="center">🐇 A PHP implementation of MySQL Protocol. 🐇</p>**

# A PHP implementation of MySQL Protocol

## 安装

### 依赖

- PHP >= 8.1
- [workerman](https://github.com/walkor/workerman) >= 4.0 【可选，`workerman`环境】

### 安装

```shel
composer require workbunny/mysql-protocol
```

## 使用

### Binary 二进制流

- `Binary`提供了二进制流和字节组之间的互转能力（注：PHP是二进制安全语言）
- `Binary`提供了基础的字节组读写操作能力，读写操作的指针相互隔离，读写指针默认从0位开始
- `payload`支持传递`字符串`、`字节数组`、`iterable类型的字节组`、`null`

```php
use Workbunny\MysqlProtocol\Utils\Binary;

$binary = new Binary("workbunny");
# 输出字节组
$binary->unpack();
# 输出字符串(输入明文则返回明文，输入二进制数据则返回二进制)
$binary->pack();
# 输出原始负载
$binary->payload();
```

#### 读

- 默认以0位开始，每次操作都会递增相应字节位置

```php
use Workbunny\MysqlProtocol\Utils\Binary;

$binary = new Binary("workbunny");

# 设置读取指针
$binary->setReadCursor();
# 获取读取指针
$binary->getReadCursor();

# 读取一个字节
$binary->readByte();
# 读取多个字节
$binary->readBytes();
# 读取一个整数(长度编码)
$binary->readLenEncInt();
# 读取一个字符串(长度编码)
$binary->readLenEncString();
# 读取一个无符号整数(长度编码)
$binary->readUB();
# 读取一个字符串(以NULL结束)
$binary->readNullTerminated();
```


#### 写

- 默认以0位开始，每次操作都会递增相应字节位置

```php
use Workbunny\MysqlProtocol\Utils\Binary;

$binary = new Binary();

# 设置写指针
$binary->setWriteCursor();
# 获取写取指针
$binary->getWriteCursor();

# 写一个字节
$binary->writeByte();
# 写多个字节
$binary->writeBytes();
# 写一个整数(长度编码)
$binary->writeLenEncInt();
# 写一个字符串(长度编码)
$binary->writeLenEncString();
# 写一个无符号整数(长度编码)
$binary->writeUB();
# 写一个字符串(以NULL结束)
$binary->writeNullTerminated();
```

### Packet 协议包

- `Packet`提供了`MySQL`协议基础的二进制包数据的解析与封装能力
- `Packet`提供`PacketInterface`自定义实现
- 默认13种`Packet`覆盖了常见`MySQL`交互动作

