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

use Wenprise\Socialite\AccessTokenInterface;
use Wenprise\Socialite\ProviderInterface;
use Wenprise\Socialite\User;

/**
 * Class DoubanProvider.
 *
 * @see http://developers.douban.com/wiki/?title=oauth2 [使用 OAuth 2.0 访问豆瓣 API]
 */
class DoubanProvider extends AbstractProvider implements ProviderInterface
{
    /**
     * {@inheritdoc}.
     */
    protected function getAuthUrl($state)
    {
        return $this->buildAuthUrlFromBase('https://www.douban.com/service/auth2/auth', $state);
    }

    /**
     * {@inheritdoc}.
     */
    protected function getTokenUrl()
    {
        return 'https://www.douban.com/service/auth2/token';
    }

    /**
     * {@inheritdoc}.
     */
    protected function getUserByToken(AccessTokenInterface $token)
    {
        $response = wp_remote_get('https://api.douban.com/v2/user/~me', [
            'headers' => [
                'Authorization' => 'Bearer '.$token->getToken(),
            ],
        ]);

        return json_decode(wp_remote_retrieve_body($response), true);
    }

    /**
     * {@inheritdoc}.
     */
    protected function mapUserToObject(array $user)
    {
        return new User([
            'id' => $this->arrayItem($user, 'id'),
            'nickname' => $this->arrayItem($user, 'name'),
            'name' => $this->arrayItem($user, 'name'),
            'avatar' => $this->arrayItem($user, 'large_avatar'),
            'email' => null,
        ]);
    }

    /**
     * {@inheritdoc}.
     */
    protected function getTokenFields($code)
    {
        return parent::getTokenFields($code) + ['grant_type' => 'authorization_code'];
    }

    /**
     * {@inheritdoc}.
     */
    public function getAccessToken($code)
    {
        $response = wp_remote_post($this->getTokenUrl(), [
            'body' => $this->getTokenFields($code),
        ]);

        return $this->parseAccessToken(wp_remote_retrieve_body($response));
    }
}
