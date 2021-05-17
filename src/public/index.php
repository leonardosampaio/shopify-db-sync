<?php
use \Psr\Http\Message\ServerRequestInterface as Request;
use \Psr\Http\Message\ResponseInterface as Response;

require '../vendor/autoload.php';
require 'helpers.php';

$dotenv = new Dotenv\Dotenv(__DIR__ . str_repeat(DIRECTORY_SEPARATOR . '..', 2));
$dotenv->load();

$config = [
  'apiKey' => $_ENV['API_KEY'],
  'secret' => $_ENV['SECRET'],
  'host' => $_ENV['HOST'],

  //debug
  'settings' => [ 
    'displayErrorDetails' => true,
    'debug'=>true
  ],
];

$app = new \Slim\App($config);

$app->get('/update-stock', function (Request $request, Response $response) {
  
  $starttime = microtime_float();

  $newResponse = $response->withHeader('Content-type', 'application/json');

  $whitelist = array(
    '127.0.0.1',
    '::1',

    //debug
    '177.91.54.38'
  );

  $clientIp = $_SERVER['REMOTE_ADDR'];

  if(in_array($clientIp, $whitelist))
  {

    $dotenv = new Dotenv\Dotenv(__DIR__ . str_repeat(DIRECTORY_SEPARATOR . '..', 2));
    $dotenv->load();

    $dbData = getAll(
      $_ENV['DB_HOST'],
      $_ENV['DB_NAME'],
      $_ENV['DB_USER'], 
      $_ENV['DB_PASSWORD']);
    
    foreach ($dbData as $shopAndAccessKey)
    {
      $shop =       $shopAndAccessKey['shop'];
      $accessToken =  $shopAndAccessKey['accessKey'];

      $skuInventoryItemId = [];

      //first location
      $shopifyLocationsResponse = performShopifyRequest(
        $shop, $accessToken, 'locations', []
      );
      $locations = $shopifyLocationsResponse['locations'];
      $locationId = $locations[0]['id'];

      $products = [];
      $nextPage = null;
      do
      {
        $params = [];
        if ($nextPage)
        {
          $params['page_info'] = $nextPage;
        }
        $shopifyProductsResponse = performShopifyRequest(
          $shop, $accessToken, 'products', $params
        );
        $products = array_merge($products, $shopifyProductsResponse['products']);
        
        $nextPage = null;
        if ($shopifyProductsResponse['next'])
        {
          preg_match('/page_info\=(.*)/', $shopifyProductsResponse['next'], $matches);
          $nextPage = $matches && $matches[1] ? $matches[1] : null;
        }
      } while ($nextPage);

      $apiCurrentStock = [];
      foreach($products as $product)
      {
        foreach($product['variants'] as $variant)
        {
          $apiCurrentStock[] = [
            'SkuId' =>    $variant['sku'],
            'quantity' => $variant['inventory_quantity']
          ];
          $skuInventoryItemId[$variant['sku']] = $variant['inventory_item_id'];
        }
      }

      $twentyMinutesAgo = date('Y-m-d\TP', strtotime('-20 minutes'));
      $orders = [];
      $nextPage = null;
      do {
        $params = [
          'created_at_min' => $twentyMinutesAgo
        ];
        if ($nextPage)
        {
          $params = [];
          $params['page_info'] = $nextPage;
        }
        $shopifyOrdersResponse = performShopifyRequest(
          $shop, $accessToken, 'orders', $params
        );
        $orders = array_merge($orders, $shopifyOrdersResponse['orders']);

        $nextPage = null;
        if ($shopifyOrdersResponse['next'])
        {
          preg_match('/page_info\=(.*)/', $shopifyOrdersResponse['next'], $matches);
          $nextPage = $matches && $matches[1] ? $matches[1] : null;
        }
      } while ($nextPage);

      $apiOrders = [];
      foreach($orders as $order)
      {
        foreach($order['line_items'] as $lineItem)
        {
          $apiOrders[] = [
            'SkuId' =>    $lineItem['sku'],
            'quantity' => $lineItem['quantity']
          ];
        }
      }

      $apiResult = callApi(
        json_encode(
          [
            'storeId' =>      $shop,
            'orders' =>       $apiOrders,
            'currentStock' => $apiCurrentStock
          ]));
      
      $newStock = json_decode($apiResult);

      $shop = $newStock->storeId;
      foreach($newStock->stock as $stock)
      {
        performShopifyRequest(
          $shop, $accessToken, 'inventory_levels/set',
            [
              'inventory_item_id' => $skuInventoryItemId[$stock->SkuId],
              'location_id' => $locationId,
              'available' => $stock->quantityDelta
            ],
            'POST'
        );
      }
    }

    return $newResponse->getBody()->write(
      json_encode(['status'=>'executed', 'elapsedTimeInSeconds'=>(microtime_float() - $starttime)]));
  }


  return $newResponse->getBody()->write(
    json_encode(['status'=>'error', 'message'=>"IP $clientIp is not whitelisted"]));
});

//call this js in from fronted (settings.php); if ok, submit key
$app->get('/validate-key', function (Request $request, Response $response) {
  $params = $request->getQueryParams();
  $newResponse = $response->withHeader('Content-type', 'application/json');
  return $newResponse->getBody()->write(
    json_encode(['is_valid'=>validateApiKey($params['key'],$params['shop'])]));
});

// install route - https://shopifysync.duckdns.org/shopify-sync/?shop=zalandointegrationtestshop.myshopify.com
$app->get('/', function (Request $request, Response $response) {
  $apiKey = $this->get('apiKey');
  $host = $this->get('host');
  $shop = $request->getQueryParam('shop');

  if (!validateShopDomain($shop)) {
   return $response->getBody()->write("Invalid shop domain!");
  }
  
  $scope = 'read_locations,read_products,read_orders,write_inventory';
  $redirectUri = $host . $this->router->pathFor('oAuthCallback');
  $installUrl = "https://{$shop}/admin/oauth/authorize?client_id={$apiKey}&scope={$scope}&redirect_uri={$redirectUri}";

  return $response->withRedirect($installUrl);
});

$app->get('/auth/shopify/callback', function (Request $request, Response $response) {

  $params = $request->getQueryParams();
  
  $dotenv = new Dotenv\Dotenv(__DIR__ . str_repeat(DIRECTORY_SEPARATOR . '..', 2));
  $dotenv->load();

  $templateParams = [];
  $templateParams['disabled'] = false;

  if (!$params['accessToken'])
  {
    $apiKey = $this->get('apiKey');
    $secret = $this->get('secret');
    $validHmac = validateHmac($params, $secret);
    $validShop = validateShopDomain($params['shop']);
    $accessToken = "";

    if ($validHmac && $validShop)
    {
      $accessToken = getAccessToken($params['shop'], $apiKey, $secret, $params['code']);
      $templateParams['accessToken'] = $accessToken;
    }
    else
    {
      return $response->getBody()->write("This request is NOT from Shopify!");
    }

  }
  else
  {
    if ($params['key'] && 
    $params['shop'] && 
    validateApiKey($params['key'], $params['shop']))
    {
      $accessToken = $params['accessToken'];
      
      //saving API key for the first time
      putDbData(
        $_ENV['DB_HOST'],
        $_ENV['DB_NAME'],
        $_ENV['DB_USER'],
        $_ENV['DB_PASSWORD'],

        $params['shop'],
        $params['key'],
        $accessToken);
    }
    else
    {
      //TODO error handling
    }
  }

  //form submit
  $templateParams['shop'] = $params['shop'];
  $templateParams['key'] = $params['key'];

  $dbData = getDbData(
    $_ENV['DB_HOST'],
    $_ENV['DB_NAME'],
    $_ENV['DB_USER'], 
    $_ENV['DB_PASSWORD'],
    
    $params['shop']);

  if ($dbData)
  {
    $templateParams['key'] = $dbData['key'];
    $templateParams['disabled']=true;

    if ($accessToken != $dbData['accessToken'])
    {
      //each reinstall changes accessKey
      updateDbData(
        $_ENV['DB_HOST'],
        $_ENV['DB_NAME'],
        $_ENV['DB_USER'], 
        $_ENV['DB_PASSWORD'],
        
        $params['shop'],
        $params['key'],
        $accessToken);
    }
  }

  require_once('settings.php');
  return $response->getBody()->write($responseBody);

})->setName('oAuthCallback');

$app->run();