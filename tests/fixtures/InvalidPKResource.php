<?php declare(strict_types=1);

namespace TestSpace;

/**
 * Test resource with invalid (empty) primary key
 * This simulates what Bootstrap would generate for a table without a primary key
 */
final class InvalidPKResource
    extends \Kingsoft\Persist\Base
    implements \Kingsoft\Persist\IPersist
{
    use \Kingsoft\Persist\Db\DBPersistTrait;

    protected ?int $id;
    protected ?string $name;

    // Persist functions - simulating what Bootstrap generates for missing PK
    public static function getPrimaryKey(): string { return ''; }
    public static function isPrimaryKeyAutoIncrement(): bool { return false; }
    public static function getTableName(): string { return '`test_table`'; }
    public static function getFields(): array {
        return [
            'id'   => ['int', 0],
            'name' => ['string', 255],
        ];
    }
}
