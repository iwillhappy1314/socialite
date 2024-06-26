<?php

/*
 * This file is part of the overtrue/socialite.
 *
 * (c) overtrue <i@overtrue.me>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Wenprise\Socialite\Providers;

use Wenprise\Socialite\AccessToken;
use Wenprise\Socialite\AccessTokenInterface;
use Wenprise\Socialite\AuthorizeFailedException;
use Wenprise\Socialite\InvalidStateException;
use Wenprise\Socialite\ProviderInterface;


/**
 * Class AbstractProvider.
 */
abstract class AbstractProvider implements ProviderInterface
{
    /**
     * Provider name.
     *
     * @var string
     */
    protected $name;


    /**
     * The client ID.
     *
     * @var string
     */
    protected $clientId;

    /**
     * The client secret.
     *
     * @var string
     */
    protected $clientSecret;

    /**
     * @var \Wenprise\Socialite\AccessTokenInterface
     */
    protected $accessToken;

    /**
     * The redirect URL.
     *
     * @var string
     */
    protected $redirectUrl;

    /**
     * The custom parameters to be sent with the request.
     *
     * @var array
     */
    protected $parameters = [];

    /**
     * The scopes being requested.
     *
     * @var array
     */
    protected $scopes = [];

    /**
     * The separating character for the requested scopes.
     *
     * @var string
     */
    protected $scopeSeparator = ',';

    /**
     * The type of the encoding in the query.
     *
     * @var int Can be either PHP_QUERY_RFC3986 or PHP_QUERY_RFC1738
     */
    protected $encodingType = PHP_QUERY_RFC1738;

    /**
     * Indicates if the session state should be utilized.
     *
     * @var bool
     */
    protected $stateless = false;


    /**
     * Create a new provider instance.
     *
     * @param string      $clientId
     * @param string      $clientSecret
     * @param string|null $redirectUrl
     */
    public function __construct($clientId, $clientSecret, $redirectUrl = null)
    {
        $this->clientId     = $clientId;
        $this->clientSecret = $clientSecret;
        $this->redirectUrl  = $redirectUrl;
    }

    /**
     * Get the authentication URL for the provider.
     *
     * @param string $state
     *
     * @return string
     */
    abstract protected function getAuthUrl($state);

    /**
     * Get the token URL for the provider.
     *
     * @return string
     */
    abstract protected function getTokenUrl();

    /**
     * Get the raw user for the given access token.
     *
     * @param \Wenprise\Socialite\AccessTokenInterface $token
     *
     * @return array
     */
    abstract protected function getUserByToken(AccessTokenInterface $token);

    /**
     * Map the raw user array to a Socialite User instance.
     *
     * @param array $user
     *
     * @return \Wenprise\Socialite\User
     */
    abstract protected function mapUserToObject(array $user);

    /**
     * Redirect the user of the application to the provider's authentication screen.
     *
     * @param string $redirectUrl
     *
     * @return mixed|string
     */
    public function redirect($redirectUrl = null)
    {
        $state = null;

        if ($redirectUrl !== null) {
            $this->redirectUrl = $redirectUrl;
        }

        if ($this->usesState()) {
            $state = $this->makeState();
        }

        return $this->getAuthUrl($state);
    }


    public function getAuthRedirectUrl($redirectUrl = null, $token = null)
    {
        if ($redirectUrl !== null) {
            $this->redirectUrl = $redirectUrl;
        }

        if ($this->usesState()) {
            $state = $this->makeState();
        }

        if ($token) {
            $state = $token;
        }

        return $this->getAuthUrl($state);
    }

    /**
     * {@inheritdoc}
     */
    public function user(AccessTokenInterface $token = null)
    {
        if ($token === null && $this->hasInvalidState()) {
            throw new InvalidStateException();
        }

        $token = $token ? : $this->getAccessToken($this->getCode());

        $user = $this->getUserByToken($token);

        $user = $this->mapUserToObject($user)
                     ->merge(['original' => $user]);

        return $user->setToken($token)
                    ->setProviderName($this->getName());
    }

    /**
     * Set redirect url.
     *
     * @param string $redirectUrl
     *
     * @return $this
     */
    public function setRedirectUrl($redirectUrl)
    {
        $this->redirectUrl = $redirectUrl;

        return $this;
    }

    /**
     * Set redirect url.
     *
     * @param string $redirectUrl
     *
     * @return $this
     */
    public function withRedirectUrl($redirectUrl)
    {
        $this->redirectUrl = $redirectUrl;

        return $this;
    }

    /**
     * Return the redirect url.
     *
     * @return string
     */
    public function getRedirectUrl()
    {
        return $this->redirectUrl;
    }

    /**
     * @param \Wenprise\Socialite\AccessTokenInterface $accessToken
     *
     * @return $this
     */
    public function setAccessToken(AccessTokenInterface $accessToken)
    {
        $this->accessToken = $accessToken;

        return $this;
    }

    /**
     * Get the access token for the given code.
     *
     * @param string $code
     *
     * @return \Wenprise\Socialite\AccessTokenInterface
     */
    public function getAccessToken($code)
    {
        if ($this->accessToken) {
            return $this->accessToken;
        }

        $response = wp_remote_post($this->getTokenUrl(), [
            'headers' => ['Accept' => 'application/json'],
            'body'    => $this->getTokenFields($code),
        ]);

        return $this->parseAccessToken(wp_remote_retrieve_body($response));
    }

    /**
     * Set the scopes of the requested access.
     *
     * @param array $scopes
     *
     * @return $this
     */
    public function scopes(array $scopes)
    {
        $this->scopes = $scopes;

        return $this;
    }

    /**
     * Indicates that the provider should operate as stateless.
     *
     * @return $this
     */
    public function stateless()
    {
        $this->stateless = true;

        return $this;
    }

    /**
     * Set the custom parameters of the request.
     *
     * @param array $parameters
     *
     * @return $this
     */
    public function with(array $parameters)
    {
        $this->parameters = $parameters;

        return $this;
    }

    /**
     * @return string
     * @throws \ReflectionException
     *
     */
    public function getName()
    {
        if (empty($this->name)) {
            $this->name = strstr((new \ReflectionClass(get_class($this)))->getShortName(), 'Provider', true);
        }

        return $this->name;
    }

    /**
     * Get the authentication URL for the provider.
     *
     * @param string $url
     * @param string $state
     *
     * @return string
     */
    protected function buildAuthUrlFromBase($url, $state)
    {
        return $url . '?' . http_build_query($this->getCodeFields($state), '', '&', $this->encodingType);
    }

    /**
     * Get the GET parameters for the code request.
     *
     * @param string|null $state
     *
     * @return array
     */
    protected function getCodeFields($state = null)
    {
        $fields = array_merge([
            'client_id'     => $this->clientId,
            'redirect_uri'  => $this->redirectUrl,
            'scope'         => $this->formatScopes($this->scopes, $this->scopeSeparator),
            'response_type' => 'code',
        ], $this->parameters);

        if ($this->usesState()) {
            $fields[ 'state' ] = $state;
        }

        return $fields;
    }

    /**
     * Format the given scopes.
     *
     * @param array  $scopes
     * @param string $scopeSeparator
     *
     * @return string
     */
    protected function formatScopes(array $scopes, $scopeSeparator)
    {
        return implode($scopeSeparator, $scopes);
    }

    /**
     * Determine if the current request / session has a mismatching "state".
     *
     * @return bool
     */
    protected function hasInvalidState()
    {
        if ($this->isStateless()) {
            return false;
        }

        $state = $_SESSION[ 'state' ];

        return ! (strlen($state) > 0 && $_GET[ 'state' ] === $state);
    }

    /**
     * Get the POST fields for the token request.
     *
     * @param string $code
     *
     * @return array
     */
    protected function getTokenFields($code)
    {
        return [
            'client_id'     => $this->clientId,
            'client_secret' => $this->clientSecret,
            'code'          => $code,
            'redirect_uri'  => $this->redirectUrl,
        ];
    }

    /**
     * Get the access token from the token response body.
     *
     * @param $body
     *
     * @return \Wenprise\Socialite\AccessTokenInterface
     */
    protected function parseAccessToken($body)
    {
        if ( ! is_array($body)) {
            $body = json_decode($body, true);
        }

        if (empty($body[ 'access_token' ])) {
            throw new AuthorizeFailedException('Authorize Failed: ' . json_encode($body, JSON_UNESCAPED_UNICODE), $body);
        }

        return new AccessToken($body);
    }

    /**
     * Get the code from the request.
     *
     * @return string
     */
    protected function getCode()
    {
        return $_GET[ 'code' ];
    }

    /**
     * Determine if the provider is operating with state.
     *
     * @return bool
     */
    protected function usesState()
    {
        return ! $this->stateless;
    }

    /**
     * Determine if the provider is operating as stateless.
     *
     * @return bool
     */
    protected function isStateless()
    {
        return ! session_id() || $this->stateless;
    }

    /**
     * Return array item by key.
     *
     * @param array  $array
     * @param string $key
     * @param mixed  $default
     *
     * @return mixed
     */
    protected function arrayItem(array $array, $key, $default = null)
    {
        if ($key === null) {
            return $array;
        }

        if (isset($array[ $key ])) {
            return $array[ $key ];
        }

        foreach (explode('.', $key) as $segment) {
            if ( ! is_array($array) || ! array_key_exists($segment, $array)) {
                return $default;
            }

            $array = $array[ $segment ];
        }

        return $array;
    }

    /**
     * Put state to session storage and return it.
     *
     * @param null $token
     *
     * @return string|bool
     */
    protected function makeState()
    {
        if ( ! session_id()) {
            return false;
        }

        $state = sha1(uniqid(wp_rand(1, 1000000), true));

        $_SESSION [ 'state' ] = $state;

        return $state;
    }
}
