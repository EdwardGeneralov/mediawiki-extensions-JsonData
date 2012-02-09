<?php
require_once('JsonSchema.php');
class JsonTreeRefTest extends PHPUnit_Framework_TestCase
{
	public function getSimpleTestData() {
		$testdata = array();
		$json = '{"a":1,"b":2,"c":3,"d":4,"e":5}';
		$schematext = '{"title": "Unrestricted JSON", "type": "any"}';
		$testdata['data'] = json_decode($json, true);
		$testdata['schema'] = json_decode($schematext, true);
		return array( $testdata );
	}

    /**
     * @dataProvider getSimpleTestData
     */
    public function testJsonSimpleTestValidate($data, $schema) {
		$schemaIndex = new JsonSchemaIndex( $schema );
        $this->assertEquals($schemaIndex->root['type'], 'any');
		$nodename = JsonUtil::getTitleFromNode($schema, "Root node");
    	$rootschema = $schemaIndex->newRef($schema, null, null, $nodename);
    	$rootjson = new JsonTreeRef($data, null, null, $nodename, $rootschema);
        $this->assertEquals($rootjson->getTitle(), 'Unrestricted JSON');
		return $schemaIndex;
    }

    /**
     * @dataProvider getSimpleTestData
     */
    public function testJsonUtilGetTitleFromNode($data, $schema) {
		$nodename = JsonUtil::getTitleFromNode($schema, "Root node");
        $this->assertEquals($nodename, "Unrestricted JSON");
		return $nodename;
    }

	public function getAddressTestData() {
		$testdata = array();
		$json = file_get_contents('example/addressexample.json');
		$schematext = file_get_contents('schemas/addressbookschema.json');
		$testdata['data'] = json_decode($json, true);
		$testdata['schema'] = json_decode($schematext, true);
		return array( $testdata );
	}

    /**
     * @dataProvider getAddressTestData
     */
    public function testJsonAddressTestValidate($data, $schema) {
		$schemaIndex = new JsonSchemaIndex( $schema );
        $this->assertEquals($schemaIndex->root['type'], 'seq');
		$nodename = JsonUtil::getTitleFromNode($schema, "Root node");
    	$rootschema = $schemaIndex->newRef($schema, null, null, $nodename);
    	$rootjson = new JsonTreeRef($data, null, null, $nodename, $rootschema);
        $this->assertEquals($rootjson->getTitle(), 'Address Book');
		return $schemaIndex;
    }
}
