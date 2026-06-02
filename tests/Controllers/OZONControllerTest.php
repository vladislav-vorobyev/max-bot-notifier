<?php
namespace TNotifyer\Controllers;

use TNotifyer\Framework\LocalTestCase;
use TNotifyer\Engine\Storage;
use TNotifyer\Engine\Response;
use TNotifyer\Engine\App;
use TNotifyer\Engine\FakeRequest;
use TNotifyer\Database\FakeDBSimple;
use TNotifyer\Providers\FakeCURL;
use TNotifyer\Providers\FakeBot;

class OZONControllerTest extends LocalTestCase
{
    const OK_RESPONSE = [
        'ok' => 1,
        'postings' => []
    ];


    /**
     * This method is called before the first test of this test class is run.
     */
    public static function setUpBeforeClass(): void
    {
        Storage::set('Request', new FakeRequest('GET', '/v1/'));
        Storage::set('Response', new Response());
        Storage::set('App', new App());
        Storage::set('DBSimple', new FakeDBSimple());
        Storage::set('CURL', new FakeCURL(self::OK_RESPONSE));
        Storage::set('Bot', new FakeBot(0));
    }

    /**
     * @dataProvider methodDataProvider
     */
    public function testMethod($name)
    {
        Storage::get('DBSimple')->reset();
        $controller = new OZONController();
        $response = $controller->{$name}();

        $this->assertInstanceof('TNotifyer\Engine\Response', $response);
        $this->assertEquals(200, $response->code);
    }

    public function methodDataProvider()
    {
        return [
            ['info'],
            ['doCheck'],
            ['doCheckStatus'],
            ['makeFBSListTest'],
            ['makeCancelledFBSListTest'],
            ['makeUnfulfilledFBSListTest'],
        ];
    }

    public function testGetPosting()
    {
        Storage::get('DBSimple')->reset();
        Storage::set('Request', new FakeRequest('GET', '/v1/?num=123'));
        $controller = new OZONController();
        $response = $controller->getPosting();
        $this->assertInstanceof('TNotifyer\Engine\Response', $response);
        $this->assertEquals(200, $response->code);
    }
}
