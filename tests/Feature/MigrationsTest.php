<?php

declare(strict_types=1);

namespace M0d1xd\ExpoNotification\Tests\Feature;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use M0d1xd\ExpoNotification\Tests\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

final class MigrationsTest extends TestCase
{
    private const array TABLES = [
        'push_tokens', 'push_notifications', 'push_notification_deliveries', 'push_notification_events',
    ];

    private function migrate(string $command = 'migrate'): void
    {
        $this->artisan($command, [
            '--path' => realpath(__DIR__.'/../../database/migrations'),
            '--realpath' => true,
        ])->assertSuccessful();
    }

    public function test_it_creates_and_drops_every_table(): void
    {
        $this->migrate();

        foreach (self::TABLES as $table) {
            $this->assertTrue(Schema::hasTable($table), $table);
        }
        $this->assertTrue(Schema::hasColumns('push_tokens', [
            'device_id', 'push_token', 'platform', 'locale', 'owner_type', 'owner_id', 'active', 'deactivated_at',
        ]));
        $this->assertTrue(Schema::hasColumns('push_notification_deliveries', [
            'push_notification_id', 'push_token_id', 'push_token', 'ticket_id', 'receipt_status', 'error_type',
            'delivered_at', 'opened_at', 'dismissed_at',
        ]));
        $this->assertTrue(Schema::hasColumns('push_notification_events', [
            'push_notification_id', 'push_token_id', 'type', 'source', 'app_state', 'occurred_at',
        ]));

        $this->migrate('migrate:rollback');

        foreach (self::TABLES as $table) {
            $this->assertFalse(Schema::hasTable($table), $table);
        }
    }

    public function test_index_and_foreign_key_names_fit_mysql_identifier_limit(): void
    {
        $this->migrate();

        foreach (self::TABLES as $table) {
            $names = [
                ...array_column(Schema::getIndexes($table), 'name'),
                ...array_filter(array_column(Schema::getForeignKeys($table), 'name')),
            ];
            foreach ($names as $name) {
                $this->assertLessThanOrEqual(64, strlen($name), "{$table}: {$name}");
            }
        }
    }

    /** @return iterable<string, array{string, string}> */
    public static function morphKeyTypes(): iterable
    {
        yield 'int' => ['int', 'integer'];
        yield 'uuid' => ['uuid', 'varchar'];
        yield 'ulid' => ['ulid', 'varchar'];
    }

    #[DataProvider('morphKeyTypes')]
    public function test_morph_owner_supports_key_types(string $keyType, string $columnType): void
    {
        config(['expo-notification.owner.key_type' => $keyType]);

        $this->migrate();

        $this->assertSame($columnType, Schema::getColumnType('push_tokens', 'owner_id'));
        $this->assertTrue($this->column('push_tokens', 'owner_id')['nullable']);
    }

    public function test_foreign_owner_adds_a_nullable_foreign_key(): void
    {
        Schema::create('accounts', function (Blueprint $table) {
            $table->id();
        });
        config([
            'expo-notification.owner.mode' => 'foreign',
            'expo-notification.owner.model' => Account::class,
            'expo-notification.owner.column' => 'account_id',
        ]);

        $this->migrate();

        $this->assertFalse(Schema::hasColumn('push_tokens', 'owner_id'));
        $this->assertTrue($this->column('push_tokens', 'account_id')['nullable']);
        $this->assertSame(
            ['account_id'],
            collect(Schema::getForeignKeys('push_tokens'))->firstWhere('foreign_table', 'accounts')['columns'],
        );
    }

    public function test_table_names_are_configurable(): void
    {
        config(['expo-notification.tables.push_tokens' => 'devices']);

        $this->migrate();

        $this->assertTrue(Schema::hasTable('devices'));
        $this->assertSame(
            'devices',
            collect(Schema::getForeignKeys('push_notification_deliveries'))->firstWhere('columns', ['push_token_id'])['foreign_table'],
        );
    }

    /** @return array<string, mixed> */
    private function column(string $table, string $name): array
    {
        return collect(Schema::getColumns($table))->firstWhere('name', $name);
    }
}

final class Account extends Model
{
    protected $table = 'accounts';
}
