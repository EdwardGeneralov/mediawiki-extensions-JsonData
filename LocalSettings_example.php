<?php
# Example PHP
require_once("$IP/extensions/JsonData/JsonData.php");

$wgJsonDataNamespace[202] = "Data";
//$wgJsonDataSchemaFile[202] = "$IP/extensions/JsonData/schemas/simpleaddr-schema.json";
$wgJsonDataSchemaArticle[202] = "Schema:SimpleAddr";
$wgJsonDataNamespace[204] = "Schema";
$wgJsonDataSchemaFile[204] = "$IP/extensions/JsonData/schemas/schemaschema.json";

$wgJsonDataNamespace[206] = "JsonConfig";
$wgJsonDataSchemaArticle[206] = "Schema:JsonConfig";

foreach ($wgJsonDataNamespace as $nsnum => $nskey) {
	$wgExtraNamespaces[$nsnum] = $nskey;
	$wgExtraNamespaces[$nsnum+1] = $nskey . "_Talk";
}
