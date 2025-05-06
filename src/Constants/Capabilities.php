<?php

declare(strict_types=1);

namespace Workbunny\MysqlProtocol\Constants;

enum Capabilities: int
{
    // new more secure passwords
    case CLIENT_LONG_PASSWORD = 1;
    // Found instead of affected rows
    case CLIENT_FOUND_ROWS = 2;
    // Get all column flags
    case CLIENT_LONG_FLAG = 4;
    // One can specify db on connect
    case CLIENT_CONNECT_WITH_DB = 8;
    // Don't allow database.table.column
    case CLIENT_NO_SCHEMA = 16;
    // Can use compression protocol
    case CLIENT_COMPRESS = 32;
    // Odbc client
    case CLIENT_ODBC = 64;
    // Can use LOAD DATA LOCAL
    case CLIENT_LOCAL_FILES = 128;
    // Ignore spaces before '('
    case CLIENT_IGNORE_SPACE = 256;
    // New 4.1 protocol This is an interactive client
    case CLIENT_PROTOCOL_41 = 512;
    // This is an interactive client
    case CLIENT_INTERACTIVE = 1024;
    // Switch to SSL after handshake
    case CLIENT_SSL = 2048;
    // IGNORE sigpipes
    case CLIENT_IGNORE_SIGPIPE = 4096;
    // Client knows about transactions
    case CLIENT_TRANSACTIONS = 8192;
    // Old flag for 4.1 protocol
    case CLIENT_RESERVED = 16384;
    // New 4.1 authentication
    case CLIENT_SECURE_CONNECTION = 32768;
    // Enable/disable multi-stmt support
    case CLIENT_MULTI_STATEMENTS = 65536;
    // Enable/disable multi-results
    case CLIENT_MULTI_RESULTS = 131072;
    case CLIENT_PLUGIN_AUTH = 524288;
    case CLIENT_CONNECT_ATTRS = 1048576;
    case CLIENT_PLUGIN_AUTH_LENENC_CLIENT_DATA = 2097152;
}

