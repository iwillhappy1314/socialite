<?php

namespace Wenprise\Socialite\Providers;

use Wenprise\Socialite\AccessToken;
use Wenprise\Socialite\AccessTokenInterface;
use Wenprise\Socialite\AuthorizeFailedException;
use Wenprise\Socialite\ProviderInterface;
use Wenprise\Socialite\User;

/**
 * Class TiktokProvider.
 *
 * @author haoliang@qiyuankeji.vip
 *
 * @see    http://open.douyin.com/platform
 */
class TiktokProvider extends AbstractProvider implements ProviderInterface
{
    /**
     * 抖音接口域名.
     *
     * @var string
     */
    protected $baseUrl = 'https://www.tiktok.com/v2/auth/authorize/';

    /**
     * 应用授权作用域.
     *
     * @var array
     */
    protected $scopes = ['user.info.basic'];

    /**
     * 获取登录页面地址.
     *
     * {@inheritdoc}
     */
    protected function getAuthUrl($state)
    {
        return $this->buildAuthUrlFromBase($this->baseUrl, $state);
    }

    /**
     * 获取授权码接口参数.
     *
     * @param string|null $state
     *
     * @return array
     */
    public function getCodeFields($state = null)
    {
        $fields = [
            'client_key'    => $this->clientId,
            'redirect_uri'  => $this->redirectUrl,
            'scope'         => $this->formatScopes($this->scopes, $this->scopeSeparator),
            'response_type' => 'code',
        ];

        if ($this->usesState()) {
            $fields[ 'state' ] = $state;
        }

        return $fields;
    }

    /**
     * 获取access_token地址.
     *
     * {@inheritdoc}
     */
    protected function getTokenUrl()
    {
        return 'https://open.tiktokapis.com/v2/oauth/token/';
    }

    /**
     * 通过code获取access_token.
     *
     * @param string $code
     *
     * @return \Wenprise\Socialite\AccessToken
     */
    public function getAccessToken($code)
    {
        $response = wp_remote_post($this->getTokenUrl(), [
            'body' => $this->getTokenFields($code),
        ]);

        return $this->parseAccessToken(wp_remote_retrieve_body($response));
    }

    /**
     * 获取access_token接口参数.
     *
     * @param string $code
     *
     * @return array
     */
    protected function getTokenFields($code)
    {
        return [
            'client_key'    => $this->clientId,
            'client_secret' => $this->clientSecret,
            'code'          => $code,
            'grant_type'    => 'authorization_code',
            'redirect_uri'  => $this->getRedirectUrl(),
        ];
    }

    /**
     * 格式化token.
     *
     * @param array|string $body
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
     * 通过token 获取用户信息.
     *
     * @param AccessTokenInterface $token
     *
     * @return array|mixed
     */
    protected function getUserByToken(AccessTokenInterface $token)
    {
        $userUrl = 'https://open.tiktokapis.com/v2/user/info/';

        $response = wp_remote_get(add_query_arg('fields', 'open_id,union_id,avatar_url,display_name,display_name', $userUrl), [
                'headers' => [
                    'Authorization' => 'Bearer ' . $token->getToken(),
                    'Content-Type'  => 'application/json',
                ],
            ]
        );

        return json_decode(wp_remote_retrieve_body($response), true);
    }

    /**
     * 格式化用户信息.
     *
     * @param array $user
     *
     * @return User
     */
    protected function mapUserToObject(array $user)
    {
        return new User([
            'id'       => $this->arrayItem($user['data']['user'], 'open_id'),
            'username' => $this->arrayItem($user['data']['user'], 'nickname'),
            'nickname' => $this->arrayItem($user['data']['user'], 'nickname'),
            'avatar'   => $this->arrayItem($user['data']['user'], 'avatar'),
        ]);
    }
}
