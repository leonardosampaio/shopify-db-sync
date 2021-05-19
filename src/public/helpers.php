<?php

function callApi($json)
{
  //TODO call API with $json
  $jsonResult = "";

  //debug
  $arr = json_decode($json);
  $shop = $arr->storeId;
  $stockDelta = [];
  foreach ($arr->currentStock as $stock)
  {
    $stockDelta[] = [
      'SkuId' => $stock->SkuId,
      'quantityDelta' => rand(1, 9999)
    ];
  }
  $jsonResult = json_encode(
    [
      'storeId'=> $shop,
      'stock'=>   $stockDelta
    ]
  );
  //debug

  return $jsonResult;
}


function microtime_float()
{
    list($usec, $sec) = explode(" ", microtime());
    return ((float)$usec + (float)$sec);
}

function validateApiKey($key, $shop)
{
  //TODO
  return true;
}

function getAll($dbhost, $db, $dbuser, $dbpass)
{
  $conn = new mysqli($dbhost, $dbuser, $dbpass, $db) or die("Connect failed: %s\n". $conn -> error);

  $stmt = $conn->prepare("SELECT shop, shopify_access_key FROM $db.clients");
  $stmt->execute();
  $stmt->bind_result($shop, $accessKey);

  $arr = [];
  while ($stmt->fetch())
  {
    $arr[] = ['shop'=>$shop, 'accessKey'=>$accessKey];
  }

  $stmt->close();
  $conn->close();

  return $arr;
}

function getDbData($dbhost, $db, $dbuser, $dbpass, $shop)
{
  $conn = new mysqli($dbhost, $dbuser, $dbpass, $db) or die("Connect failed: %s\n". $conn -> error);

  $stmt = $conn->prepare("SELECT shop, api_key, shopify_access_key FROM $db.clients WHERE shop = ?");
  $stmt->bind_param("s", $shop);
  $stmt->execute();
  $stmt->bind_result($shop, $key, $accessKey);

  if ($stmt->fetch())
  {
    $arr = 
      ['shop'=>$shop,
      'key'=>$key,
      'accessKey'=>$accessKey];
  }

  $stmt->close();
  $conn->close();

  return $arr;
}

function putDbData($dbhost, $db, $dbuser, $dbpass, $shop, $key, $accessToken)
{
  $conn = new mysqli($dbhost, $dbuser, $dbpass, $db) or die("Connect failed: %s\n". $conn -> error);

  $stmt = $conn->prepare("INSERT INTO $db.clients (id, shop, api_key, shopify_access_key) VALUES (NULL, ?,?,?)");
  $stmt->bind_param("sss", $shop, $key, $accessToken);
  $res = $stmt->execute();
  $conn->close();

  return $stmt->affected_rows;
  
}

function updateDbData($dbhost, $db, $dbuser, $dbpass, $shop, $key, $accessToken)
{
  $conn = new mysqli($dbhost, $dbuser, $dbpass, $db) or die("Connect failed: %s\n". $conn -> error);

  $stmt = $conn->prepare("UPDATE $db.clients SET api_key = ?, shopify_access_key = ? WHERE shop = ?");
  $stmt->bind_param("sss", $key, $accessToken, $shop);
  $stmt->execute();
  $conn->close();

  return $stmt->affected_rows;
  
}

// Helper method to determine if a shop domain is valid
function validateShopDomain($shop) {
  $substring = explode('.', $shop);

  // 'blah.myshopify.com'
  if (count($substring) != 3) {
    return FALSE;
  }

  // allow dashes and alphanumberic characters
  $substring[0] = str_replace('-', '', $substring[0]);
  return (ctype_alnum($substring[0]) && $substring[1] . '.' . $substring[2] == 'myshopify.com');
}

// Helper method to determine if a request is valid
function validateHmac($params, $secret) {
  $hmac = $params['hmac'];
  unset($params['hmac']);
  ksort($params);

  $computedHmac = hash_hmac('sha256', http_build_query($params), $secret);

  return hash_equals($hmac, $computedHmac);
}

// Helper method for exchanging credentials
function getAccessToken($shop, $apiKey, $secret, $code) {
  $query = array(
  	'client_id' => $apiKey,
  	'client_secret' => $secret,
  	'code' => $code
  );

  // Build access token URL
  $access_token_url = "https://{$shop}/admin/oauth/access_token";

  // Configure curl client and execute request
  $curl = curl_init();
  $curlOptions = array(
    CURLOPT_RETURNTRANSFER => TRUE,
    CURLOPT_URL => $access_token_url,
    CURLOPT_POSTFIELDS => http_build_query($query)
  );
  curl_setopt_array($curl, $curlOptions);
  $jsonResponse = json_decode(curl_exec($curl), TRUE);
  curl_close($curl);

  return $jsonResponse['access_token'];
}

// Helper method for making Shopify API requests
function performShopifyRequest($shop, $token, $resource, $params = array(), $method = 'GET') {
  $url = "https://{$shop}/admin/api/2021-04/{$resource}.json";

  $curlOptions = array(
    CURLOPT_RETURNTRANSFER => TRUE,
    CURLOPT_HEADER => TRUE
  );

  if ($method == 'GET') {
    if (!is_null($params)) {
      $url = $url . "?" . http_build_query($params);
    }
  } else {
    $curlOptions[CURLOPT_CUSTOMREQUEST] = $method;
  }

  $curlOptions[CURLOPT_URL] = $url;

  $requestHeaders = array(
    "X-Shopify-Access-Token: ${token}",
    "Accept: application/json"
  );

  if ($method == 'POST' || $method == 'PUT') {
    $requestHeaders[] = "Content-Type: application/json";

    if (!is_null($params)) {
      $curlOptions[CURLOPT_POSTFIELDS] = json_encode($params);
    }
  }

  $curlOptions[CURLOPT_HTTPHEADER] = $requestHeaders;

  $curl = curl_init();
  curl_setopt_array($curl, $curlOptions);
  $response = curl_exec($curl);
  $header_size = curl_getinfo($curl, CURLINFO_HEADER_SIZE);
  $header = substr($response, 0, $header_size);
  $body = substr($response, $header_size);
  curl_close($curl);

  //in php 8.0 this is not necessary
  preg_match('/<(.*)?>; rel="previous"/', $header, $matches);
  if ($matches && $matches[1])
  {
    $header = str_replace('<'.$matches[1],'',$header);
    $header = str_replace('>; rel="previous"','',$header);
  }

  preg_match('/<(.*)?>; rel="next"/', $header, $matches);

  $arr = json_decode($body, TRUE);
  if (isset($matches[1]) && trim($matches[1]) != '')
  {
    //2021-04 api limit: 250 itens/request
    $arr['next'] = trim($matches[1]);
  }

  return $arr;
}