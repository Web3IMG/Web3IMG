<?php

/* --------------------------------------------------------------------

  Chevereto
  https://chevereto.com/

  @author	Rodolfo Berrios A. <http://rodolfoberrios.com/>
            <inbox@rodolfoberrios.com>

  Copyright (C) Rodolfo Berrios A. All rights reserved.

  BY USING THIS SOFTWARE YOU DECLARE TO ACCEPT THE CHEVERETO EULA
  https://chevereto.com/license

  --------------------------------------------------------------------- */

use Abraham\TwitterOAuth\TwitterOAuth;

$route = function ($handler) {
    if (!CHV\Login::isPi()) {
        echo 'Route not available until the system update gets installed.';
        die();
    }

    $doing = $handler->request[0];

    if (!in_array($doing, ['google', 'facebook', 'twitter', 'vk'])) {
        return $handler->issue404();
    }

    $logged_user = CHV\Login::getUser();

    // User status override redirect
    CHV\User::statusRedirect($logged_user['status'] ?? null);

    // Detect return _REQUEST
    if ($_REQUEST['return']) {
        $_SESSION['connect_return'] = $_REQUEST['return'];
    }

    // Forbidden connection
    if (!CHV\getSetting($doing)) {
        return $handler->issue404();
    }

    $cookieName = CHV\Login::getSocialCookieName($doing);

    if ($logged_user) {
        $validate = CHV\Login::validateCookie($cookieName);
        $login_cookies = CHV\Login::getSession()['login_cookies'];
        if (
            isset($logged_user['login']['cookie_' . $doing])
            && $validate['valid']
            && in_array($validate['login_id'], $login_cookies)
        ) {
            G\redirect($logged_user['url'] . '?conn');
            return;
        }
        $logged_doing = $logged_user['login'][$doing][0] ?? null;
        $token = $logged_doing['token_hash'] ?? null;
        $secret = $logged_doing['secret'] ?? null;
    }

    /**
     * @var bool TRUE to INSERT a new login $doing
     */
    $do_insert = false;

    /**
     * @var bool TRUE to INSERT a new cookie_$doing
     */
    $do_cookie = false;

    /**
     * @var bool TRUE to attempt /connect redirection process
     */
    $redirCallback = true;

    switch ($doing) {
        case 'facebook':
            // Redirect to home on error
            if (isset($_REQUEST['state'], $_REQUEST['error'])) {
                G\redirect();
            }
            $facebook = new Facebook\Facebook([
                'app_id'     => CHV\getSetting('facebook_app_id'),
                'app_secret' => CHV\getSetting('facebook_app_secret'),
                'default_graph_version' => 'v2.8',
            ]);
            $connectURL = G\get_base_url('connect/facebook');
            $helper = $facebook->getRedirectLoginHelper();
            $accessToken = $helper->getAccessToken($connectURL);
            if (isset($accessToken)) {
                $redirCallback = false;
                $response = $facebook->get('/me?fields=id,name,cover,link,picture.type(large)', $accessToken);
                $get_user = $response->getGraphUser();
                $do_cookie = true;
                $do_insert = true;
            } elseif (isset($logged_doing)) {
                try {
                    $response = $facebook->get('/me?fields=id,name,cover,link,picture.type(large)', $token);
                    $get_user = $response->getGraphUser();
                    $redirCallback = false;
                    $do_cookie = true;
                } catch (Exception $e) {
                    $redirCallback = true;
                    $error = 'Google connect error: bad stored credentials';
                }
            }
            if ($redirCallback !== false) {
                $loginUrl = $helper->getLoginUrl($connectURL);
                G\redirect($loginUrl);
            }
            if (isset($error)) {
                unset($_SESSION['facebook']);
                throw new Exception($error, 400);
            }
            $social_pictures = [
                'avatar'        => $get_user['picture']['url'],
                'background'    => $get_user['cover']['source']
            ];
            $connect_user = [
                'id'        => $get_user['id'],
                'username'    => G\sanitize_string(G\unaccent_string($get_user['name']), true, true),
                'name'        => $get_user['name'],
                'avatar'    => $social_pictures['avatar'],
                'url'        => $get_user['link'],
                'website'    => null
            ];
            $connect_tokens = [
                'secret'    => null,
                'token_hash' => $accessToken
            ];

            break;

        case 'twitter':
            if (isset($_REQUEST['denied'])) {
                G\redirect();
            }
            $twitter = [
                'key'         => CHV\getSetting('twitter_api_key'),
                'secret'    => CHV\getSetting('twitter_api_secret')
            ];
            $error = null;
            if (isset($_REQUEST['oauth_verifier'], $_SESSION['twitter']['token'], $_SESSION['twitter']['token_secret'])) {
                $redirCallback = false;
                $twitteroauth = new TwitterOAuth($twitter['key'], $twitter['secret'], $_SESSION['twitter']['token'], $_SESSION['twitter']['token_secret']);
                $access_token = $twitteroauth->oauth("oauth/access_token", [
                    'oauth_verifier' => $_REQUEST['oauth_verifier'],
                ]);
                $twitteroauth = new TwitterOAuth($twitter['key'], $twitter['secret'], $access_token['oauth_token'], $access_token['oauth_token_secret']);
                $get_user = $twitteroauth->get('account/verify_credentials');
                if ($get_user->errors) {
                    $error = 'Twitter connect error: bad credentials or tokens';
                } else {
                    $do_insert = true;
                    $do_cookie = true;
                }
            } elseif (isset($logged_doing)) {
                $twitteroauth = new TwitterOAuth($twitter['key'], $twitter['secret'], $token, $secret);
                $get_user = $twitteroauth->get('account/verify_credentials');
                if ($get_user->errors) {
                    $redirCallback = true;
                    $error = 'Twitter connect error: bad stored credentials';
                } else {
                    $redirCallback = false;
                    $do_cookie = true;
                }
            }
            if ($redirCallback !== false) {
                try {
                    $twitteroauth = new TwitterOAuth($twitter['key'], $twitter['secret']);
                    $request_token = $twitteroauth->oauth("oauth/request_token", ["oauth_callback" => G\get_base_url('connect/twitter')]);
                    if (($request_token['oauth_callback_confirmed'] ?? false) == true) {
                        $url = $twitteroauth->url("oauth/authorize", ["oauth_token" => $request_token['oauth_token']]);
                        $_SESSION['twitter']['token'] = $request_token['oauth_token'];
                        $_SESSION['twitter']['token_secret'] = $request_token['oauth_token_secret'];
                        G\redirect($url);
                    } else {
                        throw new Exception('Twitter connect error: oauth callback not confirmed', 400);
                    }
                } catch (Exception $e) {
                    $error = $e->getMessage();
                }
            }
            if (isset($error)) {
                unset($_SESSION['twitter']);
                throw new Exception($error, 400);
            }
            $social_pictures = [
                'avatar'        => str_replace('_normal.', '.', $get_user->profile_image_url_https),
                'background'    => $get_user->profile_background_image_url
            ];
            $connect_user = [
                'id'        => $get_user->id,
                'username'    => $get_user->screen_name,
                'name'        => $get_user->name,
                'avatar'    => $social_pictures['avatar'],
                'url'        => 'http://twitter.com/' . $get_user->screen_name,
                'website'    => $get_user->entities->url ? $get_user->entities->url->urls[0]->expanded_url : null
            ];
            $connect_tokens = [
                'secret'    => $access_token['oauth_token_secret'],
                'token_hash' => $access_token['oauth_token']
            ];
            break;

        case 'google':
            $google = [
                'id'         => CHV\getSetting('google_client_id'),
                'secret'    => CHV\getSetting('google_client_secret')
            ];
            if (isset($_REQUEST['state'], $_SESSION['google']['state']) && $_SESSION['google']['state'] !== $_REQUEST['state']) {
                G\set_status_header(403);
                $handler->template = 'request-denied';
                return;
            } else {
                $_SESSION['google']['state'] = md5(uniqid(mt_rand(), true));
            }
            if (($_REQUEST['error'] ?? null) == 'access_denied') {
                G\redirect('login');
            }
            $client = new Google\Client();
            $client->setApplicationName(CHV\getSetting('website_name') . ' connect');
            $client->setClientId($google['id']);
            $client->setClientSecret($google['secret']);
            $client->setRedirectUri(G\get_base_url('connect/google'));
            if ($_SESSION['google']['state']) {
                $client->setState($_SESSION['google']['state']);
            }
            $client->setScopes([Google\Service\Oauth2::USERINFO_PROFILE]);
            $oauth2Service = new Google\Service\Oauth2($client);
            if (isset($_GET['code'])) {
                $client->fetchAccessTokenWithAuthCode($_GET['code']);
                $access_token = $client->getAccessToken();
                $redirCallback = false;
                $client->setAccessToken($access_token);
                $get_user = $oauth2Service->userinfo->get();
                if (!$get_user) {
                    $error = 'Google connect error: bad credentials or tokens';
                } else {
                    $do_insert = true;
                    $do_cookie = true;
                }
            } elseif (isset($logged_doing)) {
                try {
                    $client->setAccessToken($token);
                    $get_user = $oauth2Service->userinfo->get();
                    $redirCallback = false;
                    $do_cookie = true;
                } catch (Exception $e) {
                    $redirCallback = true;
                    $error = 'Google connect error: bad stored credentials';
                }
            }
            if ($redirCallback !== false) {
                G\redirect($client->createAuthUrl());
            }
            if (isset($error)) {
                unset($_SESSION['google']);
                throw new Exception($error, 400);
            }
            $social_pictures = [
                'avatar'        => $get_user->getPicture(),
                'background'    => null
            ];
            $connect_user = [
                'id'        => $get_user->getId(),
                'username'    => G\sanitize_string(G\unaccent_string($get_user->getName()), true, true),
                'name'        => $get_user->getName(),
                'avatar'    => $get_user->getPicture(),
                'url'        => $get_user->getLink(),
            ];
            $connect_tokens = [
                'secret'    => null,
                'token_hash' => json_encode($client->getAccessToken())
            ];
            break;

        case 'vk':
            $vk = [
                'client_id'        => CHV\getSetting('vk_client_id'),
                'client_secret'    => CHV\getSetting('vk_client_secret'),
                'redirect_uri'    => G\get_base_url('connect/vk')
            ];
            $error = null;
            $client = new \BW\Vkontakte($vk);
            if (isset($_GET['code'])) {
                $redirCallback = false;
                $client->authenticate();
                $access_token = $client->getAccessToken();
                $redirCallback = false;
                $client->setAccessToken($access_token);
                $query = [
                    'user_id' => $client->getUserId(),
                    'fields' => ['photo_200', 'site', 'domain']
                ];
                $get_user = $client->api('users.get', $query)[0] ?? null;
                if (empty($get_user)) {
                    $error = 'VK connect error: bad credentials or tokens';
                } else {
                    $do_insert = true;
                    $do_cookie = true;
                }
            } elseif (isset($logged_doing)) {
                $client->setAccessToken($token);
                $query = [
                    'user_id' => $client->getUserId(),
                    'fields' => ['photo_200', 'site', 'domain']
                ];
                $get_user = $client->api('users.get', $query)[0] ?? null;
                if (empty($get_user)) {
                    $redirCallback = true;
                    $error = 'VK connect error: bad stored credentials';
                } else {
                    $redirCallback = false;
                    $do_cookie = true;
                }
            }
            if ($redirCallback !== false) {
                G\redirect($client->getLoginUrl());
            }
            if (isset($error)) {
                unset($_SESSION['vk']);
                throw new Exception($error, 400);
            }
            $social_pictures = [
                'avatar'        => $get_user['photo_200'],
                'background'    => null
            ];
            $connect_user = [
                'id' => $get_user['id'] ?: $get_user['uid'],
                'username'    => G\sanitize_string(G\unaccent_string($get_user['first_name'] . $get_user['last_name']), true, true),
                'name'        => trim($get_user['first_name'] . ' ' . $get_user['last_name']),
                'avatar'    => $get_user['photo_200'],
                'url'        => 'http://vk.com/' . $get_user['domain'],
                'website'    => $get_user['site']
            ];
            $connect_tokens = [
                'secret'    => null,
                'token_hash' => json_encode($client->getAccessToken())
            ];
            break;
    }

    if (!empty($logged_user)) {
        $user = $logged_user;
    }

    if ($do_insert) {
        $login = CHV\Login::get(['type' => $doing, 'resource_id' => $connect_user['id']]);
        if (count($login) > 1) {
            foreach ($login as $v) {
                $isUser = CHV\User::getSingle($v['user_id']);
                if (!$isUser) {
                    CHV\Login::delete(['id' => $v['id']]);
                } else {
                    $login = $v;
                    break;
                }
            }
        } else {
            $login = $login[0];
        }
        if ($login && !$user) {
            $user = CHV\User::getSingle($login['user_id']);
        }
        if (!$user) {
            if (!CHV\Settings::get('enable_signups')) {
                _se('Signup is disabled');
                die();
            }
            // Create user (bound to social network login)
            $username = '';
            preg_match_all('/[\w]/', $connect_user['username'], $user_matches);
            foreach ($user_matches[0] as $match) {
                $username .= $match;
            }
            $baseUsername = substr(strtolower($username), 0, CHV\getSetting('username_max_length'));
            $username = $baseUsername;
            $j = 1;
            while (!CHV\User::isValidUsername($username)) {
                if(strlen($username) > CHV\getSetting('username_max_length')) {
                    $username = substr($baseUsername, 0, -strlen($j)) . $j;
                } else {
                    $username .= $j;
                }
                $j++;
            }
            $i = 1;
            while (CHV\User::getSingle($username, 'username', false)) {
                if(strlen($username) > CHV\getSetting('username_max_length')) {
                    $username = substr($baseUsername, 0, -strlen($i)) . $i;
                } else {
                    $username .= $i;
                }
                $i++;
            }
            $insert_user_values = [
                'username'    => $username,
                'name'        => $connect_user['name'],
                'status'    => CHV\getSetting('require_user_email_social_signup') ? 'awaiting-email' : 'valid',
                'website'    => $connect_user['website'],
                'timezone'    => CHV\getSetting('default_timezone'),
                'language'    => CHV\L10n::getLocale(),
            ];

            if (in_array($doing, ['twitter', 'facebook'])) {
                $insert_user_values[$doing . '_username'] = $connect_user['username'];
            }
            $inserted_user = CHV\User::insert($insert_user_values);
            $user = CHV\User::getSingle($inserted_user, 'id', true);
        }
        $login_array = [
            'user_id' => $user['id'],
            'type' => $doing,
            'resource_id' => $connect_user['id']
        ];
        CHV\Login::delete($login_array);
        $login_array = array_merge($login_array, $connect_tokens);
        $login_array = array_merge($login_array, [
            'resource_name'        => $connect_user['name'],
            'resource_avatar'    => $connect_user['avatar'],
            'resource_url'        => $connect_user['url'],
        ]);
        CHV\Login::insert($login_array);
    }

    if ($do_cookie) {
        // Insert 'cookie_twitter', checks $_COOKIE due to redirects
        if (!isset($_COOKIE[$cookieName])) {
            CHV\Login::insert([
                'user_id' => $user['id'],
                'type'      => 'cookie_' . $doing,
            ]);
        }
    }

    if ($user) {
        if ($connect_user) {
            if ($doing == 'twitter') {
                $user_array[$doing . '_username'] = $connect_user['username'];
            }
            if (is_array($user_array) && count($user_array) > 0) {
                CHV\User::update($user['id'], $user_array);
            }
        }
        if ($social_pictures) {
            // Fetch the social network images
            if (!$user['avatar']['filename'] or !$user['background']['filename']) {
                $avatar_needed = !$user ? true : !$user['avatar']['filename'];
                $background_needed = !$user ? true : !$user['background']['filename'];
                try {
                    if ($avatar_needed and $social_pictures['avatar']) {
                        CHV\User::uploadPicture($user, 'avatar', $social_pictures['avatar']);
                    }
                    if ($background_needed and $social_pictures['background']) {
                        CHV\User::uploadPicture($user, 'background', $social_pictures['background']);
                    }
                } catch (Exception $e) {
                } // Silence
            }
        }
    }

    if ($do_insert || $do_cookie) {
        $redirect_to = $_SESSION['connect_return'] ? urldecode($_SESSION['connect_return']) : $logged_user['url'];
        unset($_SESSION['connect_return'], $_SESSION[$doing]);
        if ($_SESSION['last_url']) {
            $redirect_to = $_SESSION['last_url'];
        }
        G\redirect($redirect_to);
    }

    die();
};
