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
  'settings' => [
    'displayErrorDetails' => true,
    'debug'=>true
  ],
];

$app = new \Slim\App($config);

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
  
  $scope = 'read_products,read_orders';
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

    $accessToken = $dbData['accessToken'];

    //TODO api access





    /*
    $shopifyResponse = performShopifyRequest(
      $params['shop'], $accessToken, 'products', array('limit' => 10)
    );
    $products = $shopifyResponse['products'];

    $responseBody = "<h1>Your products:</h1>";
      foreach ($products as $product) {
      var_dump($product);
        $responseBody = $responseBody . '<br>' . $product['title'];
      }

    $shopifyResponse = performShopifyRequest(
        $params['shop'], $accessToken, 'orders', array('limit' => 10)
      );
      $orders = $shopifyResponse['orders'];

    echo 'orders</p>';
    var_dump($orders);

    echo 'token</p>';
    var_dump($accessToken);*/

  }

  require_once('settings.php');
  return $response->getBody()->write($responseBody);

})->setName('oAuthCallback');

$app->run();
