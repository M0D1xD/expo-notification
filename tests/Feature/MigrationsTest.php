<?php

declare(strict_types=1);

namespace M0d1xd\ExpoNotification\Tests\Feature;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use M0d1xd\ExpoNotification\Models\PushNotification;
use M0d1xd\ExpoNotification\Models\PushNotificationDelivery;
use M0d1xd\ExpoNotification\Models\PushNotificationEvent;
use M0d1xd\ExpoNotification\Models\PushToken;
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
        $names = [
            'push_tokens' => 'app_devices',
            'notifications' => 'app_push_messages',
            'deliveries' => 'app_push_deliveries',
            'events' => 'app_push_events',
        ];
        config(['expo-notification.tables' => $names]);

        $this->migrate();

        foreach ($names as $name) {
            $this->assertTrue(Schema::hasTable($name), $name);
        }
        foreach (self::TABLES as $default) {
            $this->assertFalse(Schema::hasTable($default), $default);
        }
        foreach (['app_push_deliveries', 'app_push_events'] as $table) {
            $foreign = collect(Schema::getForeignKeys($table))->mapWithKeys(fn (array $fk) => [$fk['columns'][0] => $fk['foreign_table']]);
            $this->assertSame(['push_notification_id' => 'app_push_messages', 'push_token_id' => 'app_devices'], $foreign->sortKeys()->all(), $table);
        }
        $this->assertSame(
            array_values($names),
            [(new PushToken)->getTable(), (new PushNotification)->getTable(), (new PushNotificationDelivery)->getTable(), (new PushNotificationEvent)->getTable()],
        );

        $this->migrate('migrate:rollback');

        foreach ($names as $name) {
            $this->assertFalse(Schema::hasTable($name), $name);
        }
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
