# OpenAPI

See https://swagger.io/specification/

## api.yml

This file specifies the API contract:
- endpoints
- security schemas
- verbs
- objects exchanged

It allows to:
- create a nice modelization of supported operations
- document the operations
- create processing code automatically - which code is not very interesting to
  do by hand
- separate security and application concerns

The present api.yml file tries to match existing CAT API (2.0.x)

Later a api-new.yml is a proposal/sample of some actions of the legacy API, but using
more capabilities of openAPI.

## How to use api.yml to generate relevant code

### Requirements

Java is required, as openAPI provides a multi-language code generator writtent in
Java.
See: https://github.com/swagger-api/swagger-codegen

One can get it at the following location: https://repo1.maven.org/maven2/io/swagger/codegen/v3/swagger-codegen-cli/3.0.27/swagger-codegen-cli-3.0.27.jar

### Example: Client code in PHP

Generate client code:

```bash
$ mkdir php-client
$ java -jar swagger-codegen-cli-3.0.27.jar generate -i /path/tp/api.yml -l php -o php-client/
```

Use client code:

```php
<?php
require_once(__DIR__ . '/vendor/autoload.php');

$clientConfiguration = new Swagger\Client\Configuration();

#$clientConfiguration->setDebug(true);
#$clientConfiguration->setDebugFile("/path/to/debug/file");
$clientConfiguration->setHost("https://cat.eduroam.org/admin/API.php");

$apiInstance = new Swagger\Client\Api\AdminApi(
    new GuzzleHttp\Client(),
    $clientConfiguration
);
$body = new \Swagger\Client\Model\Command(); // \Swagger\Client\Model\Command | Command
$body->setAction(\Swagger\Client\Model\Command::ACTION_ADMIN_LIST);
$body->setApiKey("FIXME");

$paramInstId = new \Swagger\Client\Model\Parameter();
$paramInstId->setName("ATTRIB-CAT-INSTID");
$paramInstId->setValue("FIXME");

$parameters = array();
$parameters[] = $paramInstId;

$body->setParameters($parameters);

try {
    $result = $apiInstance->adminOp($body);
    print_r($result);
} catch (Exception $e) {
    echo 'Exception when calling AdminApi->adminOp: ', $e->getMessage(), PHP_EOL;
}
```

#### glitches

The following dep has to be updated :
```json
  "friendsofphp/php-cs-fixer": "~2.12"
```

## api-new.yml

In this sample, I tried to expose several concepts:
- Security definition
- Different parameters use (path, query, body)
- Different verbs - mapped to consistent actions (GET/PUT/DELETE)
- Consistent HTTP return codes (200/201/401/403...)

## Sample client code to get admins

```php
<?php
require_once(__DIR__ . '/vendor/autoload.php');

$clientConfiguration = new Swagger\Client\Configuration();

#$clientConfiguration->setDebug(true);
#$clientConfiguration->setDebugFile("/path/to/debug/file");
$clientConfiguration->setHost("https://cat.eduroam.org/admin/API.php");
$clientConfiguration->setApiKey('api_key', "FIXME");
// Uncomment below to setup prefix (e.g. Bearer) for API key, if needed
// $clientConfiguration->->setApiKeyPrefix('api_key', 'Bearer');

$apiInstance = new Swagger\Client\Api\AdminApi(
    new GuzzleHttp\Client(),
    $clientConfiguration
);

$instance_id = "FIXME"; // string | eduroam instance id

try {
    $result = $apiInstance->adminList($instance_id);
    print_r($result);
} catch (Exception $e) {
    echo 'Exception when calling AdminApi->adminList: ', $e->getMessage(), PHP_EOL;
}

```
