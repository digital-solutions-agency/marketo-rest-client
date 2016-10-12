<?php

namespace CSD\Marketo\Tests;

use CSD\Marketo\Client;
use CSD\Marketo\Response\GetActivityTypesResponse;
use CSD\Marketo\Response\GetLeadActivityResponse;
use Guzzle\Http\Message\Response;
use Guzzle\Tests\GuzzleTestCase;
use Guzzle\Tests\Service\Mock\Command\MockCommand;

/**
 * @group marketo-rest-client
 */
class MarketoSoapClientTest extends GuzzleTestCase {

    public function setUp()
    {
        parent::setUp();

        /** @var \CSD\Marketo\Client $client */
        $client = $this->_getClient();

        // Queue up a response for getCampaigns as well as getCampaign (by ID).
        $this->getServer()->enqueue($this->generateResponses(200, '{"requestId":"e6be#157b5944116","success":true,"nextPageToken":"OAPD51234567890KBPLTBBZIC7KKF5FR5Y2VQGENTYVAOZ7EF3YQ===="}', TRUE));

        $client->getPagingToken(date('c'));
    }

    /**
     * Gets the marketo rest client.
     *
     * @return \CSD\Marketo\ClientInterface
     */
    private function _getClient() {

        static $client = FALSE;

        if ($client) return $client;

        $client = Client::factory([
            'url' => $this->getServer()->getUrl(),
            'client_id' => 'example_id',
            'client_secret' => 'example_secret',
            'munchkin_id' => 'example_munchkin_id',
        ]);

        return $client;
    }

    public function testConstructor() {

        $client = $this->_getClient();

        $config = $client->getConfig()->getAll();


        self::assertNotEmpty($config['client_id'], 'The `marketo_client_id` environment variable is empty.');
        self::assertNotEmpty($config['client_secret'], 'The `marketo_client_secret` environment variable is empty.');
        self::assertNotEmpty($config['munchkin_id'], 'The `marketo_munchkin_id` environment variable is empty.');

        self::assertTrue($client instanceof \CSD\Marketo\Client);
    }

    public function testExecutesCommands()
    {
        $this->getServer()->flush();
        $this->getServer()->enqueue("HTTP/1.1 200 OK\r\nContent-Length: 0\r\n\r\n");

        $client = new Client($this->getServer()->getUrl());
        $cmd = new MockCommand();
        $client->execute($cmd);

        $this->assertInstanceOf('Guzzle\\Http\\Message\\Response', $cmd->getResponse());
        $this->assertInstanceOf('Guzzle\\Http\\Message\\Response', $cmd->getResult());
        $this->assertEquals(1, count($this->getServer()->getReceivedRequests(false)));
    }

    public function testGetCampaigns() {
        // Campaign response json.
        $response_json = '{"requestId": "f81c#157b104ca98","result": [{ "id": 1004, "name": "Foo", "description": " ", "type": "trigger", "workspaceName": "Default","createdAt": "2012-09-12T19:04:12Z","updatedAt": "2014-10-22T15:51:18Z","active": false}],"success": true}';
        // Queue up a response for getCampaigns as well as getCampaign (by ID).
        $this->getServer()->enqueue($this->generateResponses(200, [$response_json, $response_json]));

        $client = $this->_getClient();
        $campaigns = $client->getCampaigns()->getResult();

        self::assertNotEmpty($campaigns[0]['id']);
        $campaign = $client->getCampaign($campaigns[0]['id'])->getResult();
        self::assertNotEmpty($campaign[0]['name']);
        self::assertEquals($campaigns[0]['name'], $campaign[0]['name']);
    }

    public function testGetLists() {
        // Campaign response json.
        $response_json = '{"requestId":"5e2c#157b132e104","result":[{"id":1,"name":"Foo","description":"Foo description","programName":"Foo program name","workspaceName":"Default","createdAt":"2016-05-05T16:37:00Z","updatedAt":"2016-05-19T17:27:41Z"}],"success":true}';
        // Queue up a response for getLists as well as getList (by ID).
        $this->getServer()->enqueue($this->generateResponses(200, [$response_json, $response_json]));

        $client = $this->_getClient();
        $lists = $client->getLists()->getResult();

        self::assertNotEmpty($lists[0]['id']);
        $list = $client->getList($lists[0]['id'])->getResult();
        self::assertNotEmpty($list[0]['name']);
        self::assertEquals($lists[0]['name'], $list[0]['name']);
    }

    public function testLeadPartitions() {
        // Queue up a response for getLeadPartitions request.
        $this->getServer()->enqueue($this->generateResponses(200,'{"requestId":"984e#157b140b012","result":[{"id":1,"name":"Default","description":"Initial system lead partition"}],"success":true}'));

        $client = $this->_getClient();
        $partitions = $client->getLeadPartitions()->getResult();

        self::assertNotEmpty($partitions[0]['name']);
        self::assertEquals($partitions[0]['name'], 'Default');
    }

    public function testResponse() {
        // Queue up a response for getCampaigns request.
        $this->getServer()->enqueue($this->generateResponses(200,'{"requestId": "f81c#157b104ca98","result": [{ "id": 1004, "name": "Foo", "description": " ", "type": "trigger", "workspaceName": "Default","createdAt": "2012-09-12T19:04:12Z","updatedAt": "2014-10-22T15:51:18Z","active": false}],"success": true}'));

        $client = $this->_getClient();
        $response = $client->getCampaigns();

        self::assertTrue($response->isSuccess());
        self::assertNull($response->getError());
        self::assertNotEmpty($response->getRequestId());

        // No assertion but make sure getNextPageToken doesn't error out.
        $response->getNextPageToken();

        // @todo: figure out how to rest \CSD\Marketo\Response::fromCommand().
    }

    protected function generateResponses($status_code, $response_data, $add_token_response = FALSE) {
        $responses = !$add_token_response  ? [] : [
            new Response(200, NULL, '{"access_token": "0f9cc479-30ae-4d7a-b850-53bd9d44de45:sj","token_type": "bearer","expires_in": 3599,"scope": "smuvva+apiuser@tibco.com"}'),
        ];

        foreach ((array) $response_data as $item) {
            $json_string = is_array($item) ? json_encode($item) : $item;
            $responses[] = new Response($status_code, NULL, $json_string);
        }

        return $responses;
    }
}