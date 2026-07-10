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
- 覆盖 MySQL 8.x 常用交互动作，包括以下包类型：

#### 基础协议包

| 包类型 | 说明 |
|--------|------|
| `HandshakeInitialization` | 服务器握手初始化包 |
| `HandshakeResponse` | 客户端握手响应包 |
| `Ok` | OK 响应包 |
| `Error` | 错误响应包 |
| `EOF` | EOF 包 |
| `Command` | 命令包（COM_QUERY, COM_PING 等） |
| `ResultSetHeader` | 结果集头包 |
| `Field` | 字段定义包 |
| `RowData` | 行数据包 |

#### 认证相关包

| 包类型 | 说明 |
|--------|------|
| `AuthSwitchRequest` | 认证切换请求 |
| `AuthSwitchResponse` | 认证切换响应 |
| `AuthMoreDataRequest` | 全认证数据请求 |
| `AuthMoreDataResponse` | 全认证数据响应 |

#### 预处理语句包

| 包类型 | 说明 |
|--------|------|
| `StmtPrepare` | COM_STMT_PREPARE 预处理请求 |
| `StmtPrepareOk` | COM_STMT_PREPARE_OK 预处理响应 |
| `StmtExecute` | COM_STMT_EXECUTE 执行预处理语句 |

#### 复制协议包（MySQL Replication / Copy Protocol）

| 包类型 | 说明 |
|--------|------|
| `RegisterSlave` | COM_REGISTER_SLAVE 从库注册 |
| `BinlogDump` | COM_BINLOG_DUMP 请求 binlog 事件流（非 GTID） |
| `BinlogDumpGTID` | COM_BINLOG_DUMP_GTID 请求 binlog 事件流（GTID 模式） |
| `BinlogEvent` | binlog 事件解析（支持 QUERY, ROTATE, XID, TABLE_MAP, GTID, FORMAT_DESCRIPTION 等） |

### 常量

- `Capabilities` - MySQL 能力标志枚举（含 MySQL 8.x 的 `CLIENT_DEPRECATE_EOF`, `CLIENT_SESSION_TRACK` 等）
- `ServerStatus` - 服务器状态标志
- `MySQLColumnType` - MySQL 列类型常量
- `Charset` - 字符集映射（覆盖 MySQL 8.x 完整字符集）
- `Errors` - MySQL 错误码枚举

---

## 使用示例

### 协议层

`protocols/MySQL.php` 是 workerman 协议处理类，使用 `MySQL://` 前缀创建连接时自动加载，负责 TCP 分包和 `Binary` 对象解码。

```php
// 服务端监听（自动使用 Protocols\MySQL 分包）
$worker = new Worker('MySQL://0.0.0.0:8844');

// 客户端连接
$client = new AsyncTcpConnection('MySQL://127.0.0.1:3306');
```

在 `onMessage` 回调中，收到的 `$message` 是 `Binary` 对象，可直接用各 Packet 类解析。

### 1. Client — 连接 MySQL 服务器

> 完整示例见 `examples/client.php`

客户端连接 MySQL 服务器的完整流程：握手 → 认证 → 查询 → 结果解析。

```php
use Workbunny\MysqlProtocol\Packets\HandshakeInitialization;
use Workbunny\MysqlProtocol\Packets\HandshakeResponse;
use Workbunny\MysqlProtocol\Packets\Command;
use Workbunny\MysqlProtocol\Packets\Ok;
use Workbunny\MysqlProtocol\Packets\Error;
use Workbunny\MysqlProtocol\Packets\ResultSetHeader;
use Workbunny\MysqlProtocol\Packets\Field;
use Workbunny\MysqlProtocol\Packets\RowData;
use Workbunny\MysqlProtocol\Packets\EOF;
use Workbunny\MysqlProtocol\Utils\Binary;
use Workbunny\MysqlProtocol\Utils\Packet;

$client = new AsyncTcpConnection('MySQL://127.0.0.1:3306');
$client->mysqlState = 0; // 0=握手, 1=认证中, 2=就绪

$client->onMessage = function (TcpConnection $conn, Binary $binary) {
    switch ($conn->mysqlState) {
        // ── 阶段1: 接收握手包 ──────────────────────────
        case 0:
            $hs = HandshakeInitialization::unpack($binary);
            // 发送握手响应
            $conn->send(HandshakeResponse::pack([
                'packet_id'        => $hs['packet_id'] + 1,
                'capability_flags' => $hs['capability_flags'],
                'max_packet_size'  => 1073741824,
                'character_set'    => 33,
                'username'         => 'root',
                'auth_plugin'      => $hs['auth_plugin_name'],
                'auth_response'    => '', // 实际需根据插件计算
            ]));
            $conn->mysqlState = 1;
            break;

        // ── 阶段2: 认证结果 ────────────────────────────
        case 1:
            $class = Packet::getPacketClass($binary);
            if ($class === Ok::class) {
                echo "Connected!\n";
                $conn->mysqlState = 2;
                // 发送查询
                $conn->send(Command::pack([
                    'command' => Command::COM_QUERY,
                    'data'    => 'SELECT 1 AS result',
                ]));
            } elseif ($class === Error::class) {
                $err = Error::unpack($binary);
                echo "Auth failed: {$err['error_message']}\n";
                $conn->close();
            }
            break;

        // ── 阶段3: 查询结果 ────────────────────────────
        case 2:
            $class = Packet::getPacketClass($binary);
            if ($class === ResultSetHeader::class) {
                $h = ResultSetHeader::unpack($binary);
                echo "Columns: {$h['field_count']}\n";
            } elseif ($class === Field::class) {
                $f = Field::unpack($binary);
                echo "  Field: {$f['name']}\n";
            } elseif ($class === RowData::class) {
                $row = RowData::unpack($binary);
                echo "  Row: " . implode(', ', $row['values']) . "\n";
            } elseif ($class === EOF::class) {
                echo "Result end.\n";
            }
            break;
    }
};

$client->connect();
```

### 2. Proxy — MySQL 代理服务器

> 完整示例见 `examples/proxy.php`

代理服务器监听客户端连接，转发流量到后端 MySQL，同时可拦截检查协议包。

```php
use Workbunny\MysqlProtocol\Packets\Command;
use Workbunny\MysqlProtocol\Packets\Error;
use Workbunny\MysqlProtocol\Utils\Binary;
use Workbunny\MysqlProtocol\Utils\Packet;

$proxy = new Worker('MySQL://0.0.0.0:8844');

$proxy->onConnect = function (TcpConnection $client) {
    // 创建到后端 MySQL 的连接
    $backend = new AsyncTcpConnection('MySQL://127.0.0.1:3306');
    $client->backend = $backend;
    $backend->client = $client;

    // 客户端 → 后端：拦截 COM_QUERY 记录 SQL
    $client->onMessage = function (TcpConnection $client, Binary $data) use ($backend) {
        if (Packet::getPacketClass($data) === Command::class) {
            $cmd = Command::unpack($data);
            if ($cmd['command'] === Command::COM_QUERY) {
                echo "[SQL] {$cmd['data']}\n";
            }
        }
        $backend->send($data); // 转发
    };

    // 后端 → 客户端：拦截 Error 包
    $backend->onMessage = function (TcpConnection $backend, Binary $data) use ($client) {
        if (Packet::getPacketClass($data) === Error::class) {
            $err = Error::unpack($data);
            echo "[ERR] {$err['error_code']}: {$err['error_message']}\n";
        }
        $client->send($data); // 转发
    };

    // 关闭联动
    $client->onClose  = fn() => $backend->close();
    $backend->onClose = fn() => $client->close();

    $backend->connect();
};
```

### 3. Server — 模拟 MySQL 服务器

> 完整示例见 `examples/server.php`

模拟 MySQL 服务器，支持握手认证和基本查询响应，可用于测试和调试。

```php
use Workbunny\MysqlProtocol\Packets\HandshakeInitialization;
use Workbunny\MysqlProtocol\Packets\HandshakeResponse;
use Workbunny\MysqlProtocol\Packets\Command;
use Workbunny\MysqlProtocol\Packets\Ok;
use Workbunny\MysqlProtocol\Packets\ResultSetHeader;
use Workbunny\MysqlProtocol\Packets\Field;
use Workbunny\MysqlProtocol\Packets\RowData;
use Workbunny\MysqlProtocol\Packets\EOF;
use Workbunny\MysqlProtocol\Utils\Packet;

$server = new Worker('MySQL://0.0.0.0:8844');

$server->onConnect = function (TcpConnection $conn) {
    // 发送握手包
    $conn->send(HandshakeInitialization::pack([
        'server_version'      => '8.4.3-mock',
        'connection_id'       => $conn->id,
        'capability_flags'    => 0x807FFFFF,
        'character_set_index' => 255,
        'status_flags'        => 2,
        'auth_plugin_data'    => Packet::authData(21),
        'auth_plugin_name'    => 'mysql_native_password',
    ]));
    $conn->mysqlState = 0;
};

$server->onMessage = function (TcpConnection $conn, Binary $binary) {
    if ($conn->mysqlState < 1) {
        // 握手响应 → 返回 OK
        $resp = HandshakeResponse::unpack($binary);
        $conn->send(Ok::pack(['packet_id' => $resp['packet_id'] + 1]));
        $conn->mysqlState = 1;
        return;
    }
    // 命令处理
    $cmd    = Command::unpack($binary);
    $nextId = $cmd['packet_id'] + 1;
    if ($cmd['command'] === Command::COM_QUERY && str_starts_with(strtoupper($cmd['data']), 'SELECT')) {
        // 返回结果集: 头 → 字段 → EOF → 行 → EOF
        $conn->send(ResultSetHeader::pack(['packet_id' => $nextId++, 'field_count' => 1]));
        $conn->send(Field::pack(['packet_id' => $nextId++, 'name' => 'result', 'type' => 0x03]));
        $conn->send(EOF::pack(['packet_id' => $nextId++]));
        $conn->send(RowData::pack(['packet_id' => $nextId++, 'values' => ['1']]));
        $conn->send(EOF::pack(['packet_id' => $nextId++]));
    } else {
        $conn->send(Ok::pack(['packet_id' => $nextId]));
    }
};
```

### 4. Replication — MySQL 复制协议（binlog）

> 完整示例见 `examples/replication.php`

作为从库连接主库，注册复制通道，接收并解析 binlog 事件流。

```php
use Workbunny\MysqlProtocol\Packets\HandshakeInitialization;
use Workbunny\MysqlProtocol\Packets\HandshakeResponse;
use Workbunny\MysqlProtocol\Packets\Ok;
use Workbunny\MysqlProtocol\Packets\RegisterSlave;
use Workbunny\MysqlProtocol\Packets\BinlogDump;
use Workbunny\MysqlProtocol\Packets\BinlogEvent;
use Workbunny\MysqlProtocol\Utils\Binary;
use Workbunny\MysqlProtocol\Utils\Packet;

$client = new AsyncTcpConnection('MySQL://127.0.0.1:3306');
$client->mysqlState = 0;
$SERVER_ID = 100;

$client->onMessage = function (TcpConnection $conn, Binary $binary) use ($SERVER_ID) {
    switch ($conn->mysqlState) {
        // ── 握手 → 认证 ──────────────────────────────
        case 0:
            $hs = HandshakeInitialization::unpack($binary);
            $conn->send(HandshakeResponse::pack([
                'packet_id'        => $hs['packet_id'] + 1,
                'capability_flags' => $hs['capability_flags'],
                'max_packet_size'  => 1073741824,
                'character_set'    => 33,
                'username'         => 'repl',
                'auth_plugin'      => $hs['auth_plugin_name'],
                'auth_response'    => '', // 实际需计算
            ]));
            $conn->mysqlState = 1;
            break;

        // ── 认证成功 → 注册从库 ──────────────────────
        case 1:
            $ok = Ok::unpack($binary);
            echo "Connected, registering slave...\n";
            $conn->send(RegisterSlave::pack([
                'packet_id' => $ok['packet_id'] + 1,
                'server_id' => $SERVER_ID,
                'hostname'  => '',
                'user'      => '',
                'password'  => '',
                'port'      => 0,
            ]));
            $conn->mysqlState = 2;
            break;

        // ── 注册成功 → 请求 binlog dump ─────────────
        case 2:
            echo "Requesting binlog dump...\n";
            $conn->send(BinlogDump::pack([
                'packet_id'       => 0,
                'binlog_pos'      => 4,
                'flags'           => 0,
                'server_id'       => $SERVER_ID,
                'binlog_filename' => '', // 从第一个文件开始
            ]));
            $conn->mysqlState = 3;
            break;

        // ── 持续接收 binlog 事件 ─────────────────────
        case 3:
            // 跳过 4 字节 MySQL 包头，剩余为 binlog 事件
            $eventBytes = array_slice($binary->unpack(), 4);
            if (!empty($eventBytes)) {
                $event = BinlogEvent::unpack(new Binary($eventBytes));
                echo "[{$event['event_type_name']}]";
                if ($event['event_type'] === BinlogEvent::QUERY_EVENT) {
                    echo " SQL: {$event['event_data']['sql']}";
                } elseif ($event['event_type'] === BinlogEvent::GTID_EVENT) {
                    echo " GTID: {$event['event_data']['sid']}:{$event['event_data']['gno']}";
                }
                echo "\n";
            }
            break;
    }
};

$client->connect();
```

#### GTID 模式

如果主库启用了 GTID，使用 `BinlogDumpGTID` 替代 `BinlogDump`：

```php
use Workbunny\MysqlProtocol\Packets\BinlogDumpGTID;

$conn->send(BinlogDumpGTID::pack([
    'packet_id'       => 0,
    'flags'           => 0,
    'server_id'       => $SERVER_ID,
    'binlog_filename' => 'mysql-bin.000001',
    'binlog_pos'      => 4,
    'gtid_sets'       => [
        [
            'sid'       => '12345678-1234-1234-1234-123456789abc',
            'intervals' => [['start' => 1, 'end' => 100]],
        ],
    ],
]));
```

#### 支持的 binlog 事件类型

| 事件 | 说明 |
|------|------|
| `FORMAT_DESCRIPTION_EVENT` | binlog 格式描述（版本、服务器版本等） |
| `QUERY_EVENT` | SQL 语句（含 schema、SQL 文本） |
| `ROTATE_EVENT` | binlog 文件切换 |
| `XID_EVENT` | 事务提交 |
| `GTID_EVENT` | GTID 事务标识 |
| `TABLE_MAP_EVENT` | 表结构映射 |
| `WRITE_ROWS_EVENT_V2` | INSERT 行事件 |
| `UPDATE_ROWS_EVENT_V2` | UPDATE 行事件 |
| `DELETE_ROWS_EVENT_V2` | DELETE 行事件 |
| `HEARTBEAT_EVENT` | 心跳 |

## 测试

```shell
composer test
```

或直接运行 PHPUnit：

```shell
vendor/bin/phpunit
```

