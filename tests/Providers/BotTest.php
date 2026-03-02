<?php
namespace TNotifyer\Providers;

use TNotifyer\Framework\LocalTestCase;
use TNotifyer\Engine\Storage;
use TNotifyer\Engine\DI;
use TNotifyer\Engine\FakeRequest;
use TNotifyer\Database\FakeDBSimple;
use TNotifyer\Providers\FakeCURL;

class BotTest extends LocalTestCase
{
    const OK_RESPONSE = [
        'marker' => 1,
        'updates' => []
    ];

    const UPDATE_EXAMPLE = [
        'update_type' => 'message_created',
        'timestamp' => 1,
        'message' => [
            'body' => ['text' => 'test']
        ]
    ];

    /**
     * This method is called before the first test of this test class is run.
     */
    public static function setUpBeforeClass(): void
    {
        Storage::set('DBSimple', new FakeDBSimple());
    }

    public function testCreation()
    {
        Storage::get('DBSimple')->reset([['chat_id' => '11'], ['chat_id' => '22']]);
        Storage::set('CURL', new FakeCURL(['user_id' => 123]));
        Storage::set('Bot', new Bot(0, 0, 'AA', '00'));
        $this->assertEquals(
            ['11', '22'],
            Storage::get('Bot')->getMainChatsIds()
        );
    }

    /**
     * @dataProvider wrongTokenDataProvider
     */
    public function testWrongToken($response, $token)
    {
        $this->expectException('TNotifyer\Exceptions\InternalException');
        Storage::set('CURL', new FakeCURL($response));
        $bot = new Bot(0, 0, $token, '00');
    }

    public function wrongTokenDataProvider()
    {
        return [
            'wrong curl response' => ['', '00:AA'],
            // 'wrong token' => [self::OK_RESPONSE, 'AA'],
            'empty' => [self::OK_RESPONSE, null]
        ];
    }

    /**
     * @depends testCreation
     * @dataProvider getOptionDataProvider
     */
    public function testgetOption($rows, $value)
    {
        Storage::get('DBSimple')->reset($rows);
        $result = Storage::get('Bot')->getOption('test');

        // $this->outputDBHistory();
        $this->assertEquals($value, $result);
    }

    public function getOptionDataProvider()
    {
        return [
            'val' => [[['value'=>'val']], 'val'],
            'int' => [[['value'=>'::S::i:123;']], 123],
            'array' => [[['value'=>'::S::a:2:{s:1:"a";i:1;s:1:"b";s:1:"2";}']], ['a'=>1,'b'=>'2']],
            'string' => [[['value'=>'::S::abc']], '::S::abc'],
            'empty' => [[['value'=>'']], ''],
            'empty array' => [[['value'=>'::S::a:0:{}']], []],
            'true' => [[['value'=>'::S::b:1;']], true],
            'false' => [[['value'=>'::S::b:0;']], false],
            'null' => [[['value'=>'::S::N;']], null],
        ];
    }

    /**
     * @depends testCreation
     * @dataProvider setOptionDataProvider
     */
    public function testsetOption($db_reset, $value, $db_history)
    {
        Storage::get('DBSimple')->reset(...$db_reset);
        Storage::get('Bot')->setOption('test', $value);

        // $this->outputDBHistory();
        $this->assertDBHistory($db_history);
    }

    public function setOptionDataProvider()
    {
        return [
            'insert val' => [[], 'val', [
                ['INSERT INTO bot_options (bot_id, `key`, `value`) VALUES (?, ?, ?)', [0, 'test', 'val']],
                ['SELECT * FROM bot_options WHERE bot_id=? AND `key`=?', [0, 'test']],
            ]],
            'update val' => [[['value'=>'']], 'val', [
                ['UPDATE bot_options SET `value`=? WHERE bot_id=? AND `key`=?', ['val', 0, 'test']],
                ['SELECT * FROM bot_options WHERE bot_id=? AND `key`=?', [0, 'test']],
            ]],
            'int' => [[], 123, [
                ['INSERT INTO bot_options (bot_id, `key`, `value`) VALUES (?, ?, ?)', [0, 'test', '::S::i:123;']],
            ]],
            'array' => [[], ['a'=>1,'b'=>'2'], [
                ['INSERT INTO bot_options (bot_id, `key`, `value`) VALUES (?, ?, ?)', [0, 'test', '::S::a:2:{s:1:"a";i:1;s:1:"b";s:1:"2";}']],
            ]],
            'empty array' => [[], [], [
                ['INSERT INTO bot_options (bot_id, `key`, `value`) VALUES (?, ?, ?)', [0, 'test', '::S::a:0:{}']],
            ]],
            'true' => [[], true, [
                ['INSERT INTO bot_options (bot_id, `key`, `value`) VALUES (?, ?, ?)', [0, 'test', '::S::b:1;']],
            ]],
            'false' => [[], false, [
                ['INSERT INTO bot_options (bot_id, `key`, `value`) VALUES (?, ?, ?)', [0, 'test', '::S::b:0;']],
            ]],
            'null' => [[], null, [
                ['INSERT INTO bot_options (bot_id, `key`, `value`) VALUES (?, ?, ?)', [0, 'test', '::S::N;']],
            ]],
        ];
    }

    /**
     * @depends testCreation
     */
    public function testCheckUpdates()
    {
        Storage::get('DBSimple')->reset();
        Storage::set('CURL', new FakeCURL(self::OK_RESPONSE));
        $result = Storage::get('Bot')->checkUpdates();
        $this->assertEquals(self::OK_RESPONSE, $result);
    }

    /**
     * @depends testCreation
     */
    public function testWebhook()
    {
        Storage::get('DBSimple')->reset();
        Storage::set('Request', new FakeRequest(
            'POST',
            '/webhook',
            self::UPDATE_EXAMPLE,
            ['X-Max-Bot-Api-Secret' => 'AA']
        ));
        $result = Storage::get('Bot')->webhook();

        $this->assertEquals(true, $result);
        // $this->outputDBHistory();
        $this->assertDBHistory([
            ['INSERT IGNORE INTO bot_updates', [0, 1, 'message_created', json_encode(self::UPDATE_EXAMPLE)]],
        ]);
    }

    /**
     * @depends testCreation
     */
    public function testForbiddenWebhook()
    {
        // $this->expectException('TNotifyer\Exceptions\InternalException');
        Storage::set('Request', new FakeRequest(
            'POST',
            '/webhook',
            self::UPDATE_EXAMPLE,
        ));
        $result = Storage::get('Bot')->webhook();

        $this->assertEquals(false, $result);
        // $this->outputDBHistory();
        $this->assertDBHistory([
            ['INSERT INTO a_log', [0, 'error', 'Forbidden webhook', self::ANY_VALUE]],
        ]);
    }

    /**
     * @depends testCreation
     */
    public function testSetWebhook()
    {
        Storage::get('DBSimple')->reset();
        Storage::get('Bot')->setWebhook();
        
        //$this->outputDBHistory();
        $this->assertDBHistory([
            ['INSERT INTO bot_log', [0, 'subscriptions',
                '{"url":"webhook","update_types":["message_created","bot_started","user_added","user_removed","bot_added","bot_removed"]}',
                '{"marker":1,"updates":[]}'
            ]],
            ['INSERT INTO a_log', [0, 'maxbot-send', 'subscriptions']],
        ]);
    }

    /**
     * @depends testCreation
     * @dataProvider sendToMainChatsDataProvider
     */
    public function testsendToMainChats($curl, $result, $db_history)
    {
        Storage::get('DBSimple')->reset();
        Storage::set('CURL', new FakeCURL($curl));
        $_result = Storage::get('Bot')->sendToMainChats('Test msg');

        $this->assertEquals($result, $_result);
        // $this->outputDBHistory();
        $this->assertDBHistory($db_history);
    }

    public function sendToMainChatsDataProvider()
    {
        return [
            '123' => [['message' => ['body' => ['mid' => 'mid.123']]], ['11'=>'mid.123', '22'=>'mid.123'], [
                ['INSERT INTO bot_log',
                    [0, 'POST : messages?chat_id=22', '{"text":"Test msg"}', '{"message":{"body":{"mid":"mid.123"}}}']],
            ]],
            'no' => [[], [], [
                ['INSERT INTO a_log', [0, 'warning', 'Empty mid in response', '[]']],
            ]],
        ];
    }

    /**
     * @depends testCreation
     */
    public function testAlarm()
    {
        Storage::get('DBSimple')->reset();
        Storage::set('CURL', new FakeCURL([
            'message' => ['body' => ['mid' => 'mid.123']],
        ]));
        $status = Storage::get('Bot')->alarm('Test alarm', ['test'=>1]);
        $this->assertEquals(true, $status);
        $this->assertEquals(false, isset( Storage::get('DBSimple')->last_sql ));
    }

    /**
     * @depends testCreation
     * @dataProvider memberUpdateDataProvider
     */
    public function testMemberUpdate($type, $sql, $args)
    {
        Storage::get('DBSimple')->reset();
        Storage::set('CURL', new FakeCURL([
            'updates' => [[
                'timestamp' => 1,
                'update_type' =>  $type,
                'chat_id' => 11,
                'user' => ['name' => 'User']

            ]],
            'message' => ['body' => ['mid' => 'mid.123']],
        ]));
        Storage::get('Bot')->checkUpdates();

        // $this->outputDBHistory();
        $this->assertDBHistory([[$sql, $args]]);
    }

    public function memberUpdateDataProvider()
    {
        return [
            'join' => ['bot_added', 'INSERT INTO bot_chats', [0, 11, 'main', '']],
            'left' => ['bot_removed', 'DELETE FROM bot_chats', [0, 11]],
        ];
    }

    public function testgetMainChatsInfo()
    {
        Storage::set('CURL', new FakeCURL([
            "type" => "chat",
            "title" => "Title",
            "status" => "active",
            "chat_id" => 11,
        ]));
        $result = Storage::get('Bot')->getMainChatsInfo();
        $this->assertEquals(['Title (chat)', 'Title (chat)'], $result);
    }

    public function testOzonLoad()
    {
        Storage::get('DBSimple')->reset();
        $this->assertNotEmpty(DI::load('OZON'));
    }

    /**
     * @depends testCreation
     * @depends testOzonLoad
     * @dataProvider botCommandsDataProvider
     */
    public function testBotCommands($text, $db_rows, $db_history)
    {
        Storage::set('CURL', new FakeCURL([
            'message' => ['body' => ['mid' => 'mid.123']],
            'chat_id' => 1,
        ]));
        Storage::get('DBSimple')->reset($db_rows);
        $update = [
            'timestamp' => 1,
            'update_type' => 'message_created',
            'message' => [
                'body' => ['text' => $text],
                'recipient' => ['chat_id' => '00'],
            ]
        ];
        Storage::get('Bot')->checkUpdate($update);

        // $this->outputDBHistory();
        $this->assertDBHistory($db_history);
    }

    public function botCommandsDataProvider()
    {
        return [
            ['/help', [], [
                ['SELECT * FROM bot_options', [0, 'chat_00_status']]
            ]],

            ['/test', [], [
                ['INSERT INTO bot_log', [0, 'POST : messages?chat_id=22', self::ANY_VALUE, self::ANY_VALUE]],
                ['INSERT INTO a_log', [0, 'maxbot-send', 'POST : messages?chat_id=22', self::ANY_VALUE]],
            ]],

            ['/info', [], [
                ['SELECT * FROM bot_options', [0, 'chat_00_status']]
            ]],

            ['/jobs', [], [
                ['SELECT * FROM bot_options', [0, 'active-jobs']],
            ]],

            ['/job_1', [], [
                ['SELECT * FROM bot_options', [0, 'active-jobs']],
                ['INSERT INTO bot_options', [0, 'active-jobs', self::ANY_VALUE]],
                ['SELECT * FROM bot_options', [0, 'active-jobs']],
            ]],

            ['/mainchats', [], [
                ['INSERT INTO bot_log', [0, 'GET : chats/22', self::ANY_VALUE]],
                ['INSERT INTO a_log', [0, 'maxbot-send', 'GET : chats/22']],
                ['INSERT INTO bot_log', [0, 'GET : chats/11', self::ANY_VALUE]],
                ['INSERT INTO a_log', [0, 'maxbot-send', 'GET : chats/11']],
            ]],

            ['/X_1', [], [
                ['INSERT INTO bot_log', [0, 'GET : chats/22', self::ANY_VALUE]],
                ['INSERT INTO a_log', [0, 'maxbot-send', 'GET : chats/22']],
                ['INSERT INTO bot_log', [0, 'GET : chats/11', self::ANY_VALUE]],
                ['INSERT INTO a_log', [0, 'maxbot-send', 'GET : chats/11']],
                ['DELETE FROM bot_chats WHERE bot_id = ? AND chat_id = ?', [0, '11']],
            ]],

            ['/ozon', [], [
                ['SELECT * FROM bot_options', [0, 'chat_00_status']],
            ]],

            ['/ozonsetid', [], [
                ['INSERT INTO bot_options', [0, 'chat_00_status', '/ozonsetid']]
            ]],

            ['123', [['value'=>'/ozonsetid']], [
                ['UPDATE bot_options SET', ['', 0, 'chat_00_status']],
                ['SELECT * FROM bot_options', [0, 'chat_00_status']],
                ['UPDATE bot_options SET', ['123', 0, Bot::ON_OZON_CLI_ID]],
            ]],

            ['/ozonsetkey', [], [
                ['INSERT INTO bot_options', [0, 'chat_00_status', '/ozonsetkey']]
            ]],

            ['123', [['value'=>'/ozonsetkey']], [
                ['UPDATE bot_options SET', ['', 0, 'chat_00_status']],
                ['SELECT * FROM bot_options', [0, 'chat_00_status']],
                ['UPDATE bot_options SET', [self::ANY_VALUE, 0, Bot::ON_OZON_API_KEY]],
            ]],

            ['/cancel', [['value'=>'/ozonsetkey']], [
                ['UPDATE bot_options SET', ['', 0, 'chat_00_status']],
                ['SELECT * FROM bot_options', [0, 'chat_00_status']],
                ['SELECT * FROM bot_options', [0, 'chat_00_status']],
            ]],
        ];
    }

    /**
     * @depends testCreation
     */
    public function testFailBotCommand()
    {
        Storage::get('DBSimple')->reset();
        $update = [
            'timestamp' => 1,
            'update_type' => 'message_created',
            'message' => [
                'body' => ['text' => '/info'],
                'recipient' => ['chat_id' => '11'],
            ]
        ];
        Storage::get('Bot')->checkUpdate($update);

        // $this->outputDBHistory();
        $this->assertDBHistory([[
            'INSERT INTO a_log', [0, 'warning', 'Access forbidden from the chat 11']
        ]]);
    }

    /**
     * @depends testCreation
     * @dataProvider getNextJobsTodoDataProvider
     */
    public function testgetNextJobsTodo($bot_jobs, $date_time, $jobs_todo)
    {
        $_SERVER['BOT_JOBS'] = $bot_jobs;
        $result = Storage::get('Bot')->getNextJobsTodo(new \DateTime($date_time));
        
        $this->assertEquals($jobs_todo, $result);
    }

    public function getNextJobsTodoDataProvider()
    {
        return [
            ['/ozon:1,11,21,31,41,51|/wb:22:00', '2026-01-01 12:13', [
                '/ozon' => '2026-01-01T12:21:00+00:00',
                '/wb' => '2026-01-01T22:00:00+00:00',
            ]],
            ['/ozon:1,11,21,31,41,51|/wb:22:22|/test:*/5', '2026-01-01 23:13', [
                '/ozon' => '2026-01-01T23:21:00+00:00',
                '/wb' => '2026-01-02T22:22:00+00:00',
                '/test' => '2026-01-01T23:15:00+00:00',
            ]],
        ];
    }

    /**
     * @depends testCreation
     * @dataProvider runJobsTodoDataProvider
     */
    public function testrunJobsTodo($db_reset, $jobs_todo, $date_time, $db_history)
    {
        Storage::get('DBSimple')->reset(...$db_reset);
        Storage::get('Bot')->runJobsTodo($jobs_todo, new \DateTime($date_time));
        
        // $this->outputDBHistory();
        $this->assertDBHistory($db_history);
    }

    public function runJobsTodoDataProvider()
    {
        return [
            [
                [[['value' => '::S::a:1:{s:5:"/ozon";b:1;}']]],
                ['/ozon' => '2026-01-02T00:01:00+00:00'],
                '2026-01-02 01:13', [
                    ['SELECT * FROM bot_options', [0, 'active-jobs']],
                ]
            ],
        ];
    }

    /**
     * @depends testCreation
     * @depends testgetNextJobsTodo
     * @depends testrunJobsTodo
     * @dataProvider runJobsDataProvider
     */
    public function testrunJobs($bot_jobs, $db_reset, $db_history)
    {
        $_SERVER['BOT_JOBS'] = $bot_jobs;
        Storage::get('DBSimple')->reset(...$db_reset);
        Storage::get('Bot')->runJobs();
        
        // $this->outputDBHistory();
        $this->assertDBHistory($db_history);
    }

    public function runJobsDataProvider()
    {
        return [
            ['', [], [
                ['INSERT INTO bot_options', [0, 'jobs-todo', '::S::a:0:{}']],
                ['SELECT * FROM bot_options', [0, 'jobs-todo']],
                ['SELECT * FROM bot_options', [0, 'active-jobs']],
            ]],
            ['/ozon:1,21,41|/wb:22:00|/wb-check-discount:10:01', [], [
                ['INSERT INTO bot_options', [0, 'jobs-todo', self::ANY_VALUE]],
            ]],
        ];
    }

    /**
     * @depends testCreation
     */
    public function testSendTbotDayActivity()
    {
        Storage::get('DBSimple')->reset();
        Storage::get('Bot')->sendTbotDayActivity();
        $sql = 'SELECT count(*) FROM a_log WHERE created BETWEEN DATE_SUB(NOW(),INTERVAL 1 DAY)';
        $this->assertEquals($sql, substr( Storage::get('DBSimple')->last_sql, 0, strlen($sql) ));
    }

    /**
     * @depends testCreation
     */
    public function testPingWebsites()
    {
        Storage::get('DBSimple')->reset([['id'=>1, 'url'=>'http://www.test.com', 'active'=>false]]);
        Storage::get('Bot')->pingWebsites();
        $sql = 'UPDATE a_websites SET active=1, updated=NOW() WHERE id=1';
        $this->assertEquals($sql, substr( Storage::get('DBSimple')->last_sql, 0, strlen($sql) ));
    }

}
