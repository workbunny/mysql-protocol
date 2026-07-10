<?php

declare(strict_types=1);

namespace Workbunny\MysqlProtocol\Constants;

/**
 * MySQL 服务器状态标志
 *
 * @see https://dev.mysql.com/doc/dev/mysql-server/latest/mysql__com_8h.html
 */
final class ServerStatus
{
    /** In a transaction */
    public const SERVER_STATUS_IN_TRANS = 0x0001;
    /** Autocommit enabled */
    public const SERVER_STATUS_AUTOCOMMIT = 0x0002;
    /** More results exist */
    public const SERVER_MORE_RESULTS_EXISTS = 0x0008;
    /** No good index used */
    public const SERVER_QUERY_NO_GOOD_INDEX_USED = 0x0010;
    /** No index used */
    public const SERVER_QUERY_NO_INDEX_USED = 0x0020;
    /** Cursor exists */
    public const SERVER_STATUS_CURSOR_EXISTS = 0x0040;
    /** Last row sent */
    public const SERVER_STATUS_LAST_ROW_SENT = 0x0080;
    /** Database dropped */
    public const SERVER_STATUS_DB_DROPPED = 0x0100;
    /** No backtrack in cursor */
    public const SERVER_STATUS_NO_BACKSLASH_ESCAPES = 0x0200;
    /** Metadata changed */
    public const SERVER_STATUS_METADATA_CHANGED = 0x0400;
    /** Query was slow */
    public const SERVER_QUERY_WAS_SLOW = 0x0800;
    /** PS out params */
    public const SERVER_PS_OUT_PARAMS = 0x1000;
    /** In read-only transaction */
    public const SERVER_STATUS_IN_TRANS_READONLY = 0x2000;
    /** Session state changed */
    public const SERVER_SESSION_STATE_CHANGED = 0x4000;
}
