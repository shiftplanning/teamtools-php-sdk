<?php

namespace teamtools;

use teamtools\Entities\Entity;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\ServerException;

class TeamToolsClient
{

    const STRIPE_HANDLER_ENDPOINT = 'stripe';

    protected $authDomain = 'http://develop-auth.dev.teamtools.io/';
    protected $apiDomain = 'http://develop-api.dev.teamtools.io/';
    // protected $authDomain = 'http://auth.teamtools.local/';
    // protected $apiDomain = 'http://api.teamtools.local/';
    protected $guzzleClient;
    protected $accessObject;
    protected static $instance;
    protected $salt;

    public static function initialize(array $config)
    {
        if (null === static::$instance) {
            static::$instance = new static($config);
        }

        Entity::registerClient(static::$instance);
    }

    public static function getInstance()
    {
        if (null === static::$instance) {
            throw new \Exception("Client not initialized.");
        }

        return static::$instance;
    }

    public static function handleStripe()
    {
        $client = static::getInstance();

        $input = file_get_contents('php://input');
        $event = json_decode($input, true);

        return $client->doRequest('post', $event, TeamToolsClient::STRIPE_HANDLER_ENDPOINT);
    }

    protected function __construct(array $config)
    {
        $this->guzzleClient = new \GuzzleHttp\Client();
        $this->salt = $config['salt'];

        $authData = [
            'client_id'     => $config['client_id'],
            'client_secret' => $config['client_secret'],
            'grant_type'    => 'client_credentials'
        ];

        $response = $this->doRequest('post', $authData, 'access_token', 'auth');

        $this->accessObject = json_decode($response);
    }

    public function getSalt()
    {
        return $this->salt;
    }

    public function getAccessToken()
    {
        return $this->accessObject->access_token;
    }

    public function doRequest($method, $data, $uri, $resource = 'api', $decode = true)
    {
        $requestDataType = $method == 'get' ? 'query' : 'json';

        if ($resource == 'api') {
            $domain = $this->apiDomain;
            $data['access_token'] = $this->accessObject->access_token;
        } elseif ($resource == 'auth') {
            $domain = $this->authDomain;
        }

        try {
            $response = $this->guzzleClient->$method($domain . $uri, [$requestDataType => $data]);
        } catch (ClientException $ce) {
            die($ce->getResponse()->getBody());
        } catch (ServerException $se) {
            die($se->getResponse()->getBody());
        }

        return $response->getBody();
    }
}
