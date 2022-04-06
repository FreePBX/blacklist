<?php 
namespace FreepPBX\blacklist\utests;

require_once('../api/utests/ApiBaseTestCase.php');

use FreePBX\modules\Blacklist;
use Exception;
use FreePBX\modules\Api\utests\ApiBaseTestCase;

class BlacklistGQLTest extends ApiBaseTestCase {
	protected static $blacklist;
        
    /**
     * setUpBeforeClass
     *
     * @return void
     */
    public static function setUpBeforeClass() {
      parent::setUpBeforeClass();
      self::$blacklist = self::$freepbx->blacklist;
    }
        
    /**
     * tearDownAfterClass
     *
     * @return void
     */
    public static function tearDownAfterClass() {
      parent::tearDownAfterClass();
    }


    /**
     * test for fetching all blacklists
     * **/
    public function test_allBlacklists_whenAllIsWell_shouldReturnAllNumbers()
    {
      $mockblacklist = $this->getMockBuilder(\FreePBX\modules\blacklist\Blacklist::class)
			->disableOriginalConstructor()
			->disableOriginalClone()
			->setMethods(array('getBlacklist'))
			->getMock();

      $mockblacklist->method('getBlacklist')
			->willReturn(array(
                    array(
                      "number" => 99912,
                      "description"=>"blacklist number"
                    )
                  ));

      self::$freepbx->Blacklist = $mockblacklist; 

      $response = $this->request("
									query{
                    allBlacklists{
                      blacklists {
                          id
                          description
                          number
                        }
                      }
                  }");

      $json = (string)$response->getBody();
      $this->assertEquals('{"data":{"allBlacklists":{"blacklists":[{"id":"YmxhY2tsaXN0Ojk5OTEy","description":"blacklist number","number":"99912"}]}}}', $json);
      //status 200 success check
		  $this->assertEquals(200, $response->getStatusCode());
    }

    /**
     * test to fetch blacklist settings
     */
    public function test_blacklistSettings_whenAllIsWell_shouldReturnBlockUnknownAndDestination()
    {
      $mockblacklist = $this->getMockBuilder(\FreePBX\modules\blacklist\Blacklist::class)
			->disableOriginalConstructor()
			->disableOriginalClone()
			->setMethods(array('blockunknownGet','destinationGet'))
			->getMock();

      $mockblacklist->method('blockunknownGet')
			->willReturn(1);

      $mockblacklist->method('destinationGet')
			->willReturn('from-did-direct,100,1');

      self::$freepbx->Blacklist = $mockblacklist;

      $response = $this->request("{
                            blacklistSettings{
                                blockUnknown
                                destination
                            }
                          } ");

		  $json = (string)$response->getBody();
      $this->assertEquals('{"data":{"blacklistSettings":{"blockUnknown":true,"destination":"Extensions: 100 100"}}}', $json);
      //status 200 success check
		  $this->assertEquals(200, $response->getStatusCode());
    }

    /**
     * test to add blacklist number
     */
    public function test_addBlacklist_whenAllIsWell_shouldReturnAddedBlacklistNumber()
    {
      $mockblacklist = $this->getMockBuilder(\FreePBX\modules\blacklist\Blacklist::class)
			->disableOriginalConstructor()
			->disableOriginalClone()
			->setMethods(array('numberAdd','getBlacklist'))
			->getMock();

      $mockblacklist->method('numberAdd')
			->willReturn('345678');

      $mockblacklist->method('getBlacklist')
			->willReturn(array(
                    array(
                      "number" => 345678,
                      "description"=>"blacklist number"
                    )
                  ));

      self::$freepbx->Blacklist = $mockblacklist;

      $response = $this->request('mutation {
                      addBlacklist(input: {
                      number: "345678"
                      description:"test description"
                      clientMutationId : "12432"
                    }) {
                          clientMutationId
                      }
                  }');

      $json = (string)$response->getBody();
      $this->assertEquals('{"data":{"addBlacklist":{"clientMutationId":"12432"}}}', $json);
      //status 200 success check
		  $this->assertEquals(200, $response->getStatusCode());
    }

    /**
     * test to remove blacklist number
     */
    public function test_removeBlacklist_whenAllIsWell_shouldReturnRemovedBlacklistNumber()
    {
      $mockblacklist = $this->getMockBuilder(\FreePBX\modules\blacklist\Blacklist::class)
			->disableOriginalConstructor()
			->disableOriginalClone()
			->setMethods(array('numberDel'))
			->getMock();

      $mockblacklist->method('numberDel')
			->willReturn('345678');

      self::$freepbx->Blacklist = $mockblacklist;

      $response = $this->request('mutation {
                      removeBlacklist(input: {
                      number: "345678"
                      clientMutationId : "12432"
                    }) {
                          clientMutationId
                      }
                  }');

      $json = (string)$response->getBody();
      $this->assertEquals('{"data":{"removeBlacklist":{"clientMutationId":"12432"}}}', $json);
      //status 200 success check
		  $this->assertEquals(200, $response->getStatusCode());
    }


    /***
     * test to set blacklist settings
     */
    public function test_setBlacklistSettings_whenAllIsWell_shouldReturnSettings()
    {
      $mockblacklist = $this->getMockBuilder(\FreePBX\modules\blacklist\Blacklist::class)
			->disableOriginalConstructor()
			->disableOriginalClone()
			->setMethods(array('destinationSet','blockunknownSet'))
			->getMock();

      $mockblacklist->method('destinationSet')
			->willReturn(true);

      $mockblacklist->method('blockunknownSet')
			->willReturn('from-did-direct,100,1');

      self::$freepbx->Blacklist = $mockblacklist;

      $response = $this->request('
                        mutation {
                            setBlacklistSettings(input: {
                            blockUnknown: true
                            destination:"from-did-direct,100,1",
                            clientMutationId: "12432"
                          }) {
                              clientMutationId
                              blockUnknown
                              destination
                          }
                        }');

      $json = (string)$response->getBody();
      $this->assertEquals('{"data":{"setBlacklistSettings":{"clientMutationId":"12432","blockUnknown":true,"destination":"from-did-direct,100,1"}}}', $json);
      //status 200 success check
		  $this->assertEquals(200, $response->getStatusCode());
    }

    /**
     * test to fetch particular blacklist number
     */
    public function test_blacklist_whenAllIsWell_shouldReturnBlacklist()
    {
      $mockblacklist = $this->getMockBuilder(\FreePBX\modules\blacklist\Blacklist::class)
			->disableOriginalConstructor()
			->disableOriginalClone()
			->setMethods(array('getBlacklist'))
			->getMock();

      $mockblacklist->method('getBlacklist')
			->willReturn(array(
                    array(
                      "number" => "345678",
                      "description"=>"blacklist number"
                    ),
                    array(
                      "number" => "234234",
                      "description"=>"blacklist 234324"
                    )
                  ));

      self::$freepbx->Blacklist = $mockblacklist;

      $response = $this->request('{
                      blacklist(id:"234234") {
                              id
                              description
                              number
                          }
                  }');

      $json = (string)$response->getBody();
      $this->assertEquals('{"data":{"blacklist":{"id":"YmxhY2tsaXN0OjIzNDIzNA==","description":"blacklist 234324","number":"234234"}}}', $json);
      //status 200 success check
      $this->assertEquals(200, $response->getStatusCode());
    }

}
