<?php
namespace TNotifyer\Providers;

use TNotifyer\Framework\LocalTestCase;
use TNotifyer\Engine\Storage;
use TNotifyer\Engine\FakeRequest;
use TNotifyer\Database\FakeDBSimple;
use TNotifyer\Providers\FakeCURL;

class OZONProviderTest extends LocalTestCase
{
    const POSTING_EXAMPLE = [
        "status" => "awaiting_packaging",
        "substatus" => "posting_created",
        "order_id" => 34454315739,
        "products" => [
            [
                "sku" => 2230185896,
                "imei" => [],
                "name" => "Стелька арамидная, 44",
                "price" => "5500.0000",
                "offer_id" => "стелька_44",
                "quantity" => 1,
                "currency_code" => "RUB",
                "is_blr_traceable" => false,
                "is_marketplace_buyout" => false
            ],
        ],
        "order_number" => "36615787-0025",
        "posting_number" => "36615787-0025-1",
        "in_process_at" => "2026-01-30T19:05:50Z",
    ];

    const CANCELLED_EXAMPLE = [
        "status" => "cancelled",
        "substatus" => "posting_canceled",
        "order_id" => 34454315739,
        "products" => [
            [
                "sku" => 2230185896,
                "imei" => [],
                "name" => "Стелька арамидная, 44",
                "price" => "5500.0000",
                "offer_id" => "стелька_44",
                "quantity" => 1,
                "currency_code" => "RUB",
                "is_blr_traceable" => false,
                "is_marketplace_buyout" => false
            ],
        ],
        "order_number" => "36615787-0025",
        "posting_number" => "36615787-0025-1",
        "in_process_at" => "2026-01-30T19:05:50Z",
        "cancellation" => [
            "cancel_reason" => "Покупатель отменил заказ: не устроил срок доставки",
            "cancel_reason_id" => 505,
            "cancellation_type" => "client",
            "cancelled_after_ship" => false,
            "cancellation_initiator" => "Клиент",
            "affect_cancellation_rating" => false
        ],
    ];

    /**
     * This method is called before the first test of this test class is run.
     */
    public static function setUpBeforeClass(): void
    {
        Storage::set('DBSimple', new FakeDBSimple());
        Storage::set('Bot', new FakeBot(0));
    }

    public function testCreation()
    {
        Storage::get('DBSimple')->reset();
        Storage::set('OZON', new OZONProvider('00','AA'));
        $this->assertEmpty(Storage::get('OZON')->lastErrorMessage());
    }

    public function testgetOrderText()
    {
        Storage::get('DBSimple')->reset();
        Storage::set('OZON', new OZONProvider('00','AA'));
        $this->assertNotEmpty(Storage::get('OZON')->getOrderText(self::POSTING_EXAMPLE));
    }

    /**
     * @depends testCreation
     */
    public function testCheckPosting()
    {
        Storage::get('DBSimple')->reset();
        $result = Storage::get('OZON')->checkPosting(self::POSTING_EXAMPLE);

        $this->assertEquals(true, $result);
        $this->assertNotEmpty(Storage::get('Bot')->last_main_msg);
        // $this->outputDBHistory();
        $this->assertDBHistory([
            ['INSERT INTO posting_status', [0, 'ozon', "36615787-0025-1", "awaiting_packaging", 'posting_created', '', '{"11":99}', "2026-01-30T19:05:50Z", "awaiting_packaging", 'posting_created']],
            ['INSERT IGNORE INTO postings', [0, 'ozon', "36615787-0025-1", "awaiting_packaging+posting_created", json_encode(self::POSTING_EXAMPLE)]],
        ]);
    }

    /**
     * @depends testCreation
     * @dataProvider checkTimeDataProvider
     */
    public function testGetLastCheckTime($rows, $value)
    {
        Storage::get('DBSimple')->reset($rows);
        $result = Storage::get('OZON')->getLastCheckTime();

        // $this->outputDBHistory();
        $this->assertDBHistory([[
            'SELECT TIME_TO_SEC( TIMEDIFF( NOW(), created ) ) AS sec, created FROM a_log WHERE bot_id=? AND type=? AND message=? ORDER BY id DESC LIMIT ?',
            [0, 'check', 'OZON', 1]
        ]]);
        $this->assertEquals($value, $result);
    }
    
    public function checkTimeDataProvider()
    {
        return [
            '115 seconds' => [ [['sec'=>'115']], 115 ],
            'empty' => [ [], 0 ]
        ];
    }

    /**
     * @depends testGetLastCheckTime
     * @depends testCreation
     */
    public function testDoCheckNew()
    {
        Storage::get('DBSimple')->reset();
        Storage::set('CURL', new FakeCURL([
            'postings' => [self::POSTING_EXAMPLE],
        ]));
        $ozon = Storage::get('OZON');
        $ozon->doCheckNew();

        $this->assertEmpty($ozon->lastErrorMessage());
        $this->assertNotEmpty(Storage::get('Bot')->last_main_msg);
        // $this->outputDBHistory();
        $this->assertDBHistory([[
            'INSERT INTO a_log', [0, 'check', 'OZON']
        ]]);
    }

    /**
     * @depends testCreation
     */
    public function testdoCheckCancelled()
    {
        Storage::get('DBSimple')->reset(['status'=>'']);
        Storage::set('CURL', new FakeCURL([
            'postings' => [self::CANCELLED_EXAMPLE],
        ]));
        $ozon = Storage::get('OZON');
        $ozon->doCheckCancelled();

        $this->assertEmpty($ozon->lastErrorMessage());
        $this->assertNotEmpty(Storage::get('Bot')->last_main_msg);
        $this->assertEquals(4, count(explode("\n", Storage::get('Bot')->last_main_msg)));
        $this->assertEquals(["-1001995445406"=>201], (array)json_decode(json_encode(["-1001995445406"=>201])));
        // $this->outputDBHistory();
        $this->assertDBHistory([
            ['SELECT * FROM posting_status', [0, 'ozon', '36615787-0025-1', 1]],
            ['SELECT * FROM posting_status', [0, 'ozon', '36615787-0025-1', 1]],
            ['INSERT INTO posting_status', [0, 'ozon', '36615787-0025-1', 'cancelled', 'posting_canceled', '', '[]', '2026-01-30T19:05:50Z', 'cancelled', 'posting_canceled']],
            ['INSERT IGNORE INTO postings', [0, 'ozon', '36615787-0025-1', 'cancelled+posting_canceled', self::ANY_VALUE]],
            ['SELECT * FROM postings', [0, 'ozon', '36615787-0025-1', 1]],
        ]);
    }

    /**
     * @depends testCreation
     * @dataProvider getUnfinishedPeriodDataProvider
     */
    public function testgetUnfinishedPeriod($rows, $value)
    {
        Storage::get('DBSimple')->reset($rows);
        $result = Storage::get('OZON')->getUnfinishedPeriod();

        // $this->outputDBHistory();
        $this->assertDBHistory([[
            "SELECT DATEDIFF( NOW(), created ) AS days, created FROM posting_status WHERE bot_id=? AND type=? AND status NOT IN ('delivered', 'cancelled') ORDER BY created ASC LIMIT ?",
            [0, 'ozon', 1]
        ]]);
        $this->assertEquals($value, $result);
    }
    
    public function getUnfinishedPeriodDataProvider()
    {
        return [
            '11 days' => [ [['days'=>'11']], 11 ],
            'empty' => [ [], 0 ]
        ];
    }

    /**
     * @depends testCreation
     */
    public function testdoCheckStatus()
    {
        Storage::get('DBSimple')->reset(['status'=>'']);
        Storage::set('CURL', new FakeCURL([
            'postings' => [self::CANCELLED_EXAMPLE],
        ]));
        $ozon = Storage::get('OZON');
        $ozon->doCheckStatus();

        $this->assertEmpty($ozon->lastErrorMessage());
        $this->assertNotEmpty(Storage::get('Bot')->last_main_msg);
        $this->assertEquals(4, count(explode("\n", Storage::get('Bot')->last_main_msg)));
        // $this->outputDBHistory();
        $this->assertDBHistory([
            ['INSERT INTO a_log', [0, 'check-status', 'OZON']],
            ['SELECT * FROM posting_status', [0, 'ozon', '36615787-0025-1', 1]],
            ['SELECT * FROM posting_status', [0, 'ozon', '36615787-0025-1', 1]],
            ['INSERT INTO posting_status', [0, 'ozon', '36615787-0025-1', 'cancelled', 'posting_canceled', '', '[]', '2026-01-30T19:05:50Z', 'cancelled', 'posting_canceled']],
        ]);
    }

    /**
     * @depends testGetLastCheckTime
     * @depends testCreation
     * @dataProvider wrongCheckDataProvider
     */
    public function testWrongDoCheck($data)
    {
        $this->expectException(\TNotifyer\Exceptions\ExternalRequestException::class);
        Storage::set('CURL', new FakeCURL($data));
        Storage::get('OZON')->doCheck();
    }
    
    public function wrongCheckDataProvider()
    {
        return [
            'wrong struct' => [['result' => []]],
            'empty' => [[]],
            'bad response' => [false],
        ];
    }

}
