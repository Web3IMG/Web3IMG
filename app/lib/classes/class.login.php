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

namespace CHV;

use G;
use Exception;
use LogicException;

/**
 * PrePi suffix refers to functions before Chevereto 3.14.0 (pi number)
 * @package CHV
 */
class Login
{
    /** User::get */
    protected static $logged_user;

    /** Used when handling signup process */
    protected static $signup;

    /** The login "session" properties */
    protected static $session;

    protected static $social_services = [
        'facebook' => 'Facebook',
        'twitter' => 'Twitter',
        'google' => 'Google',
        'vk' => 'VK',
    ];

    const COOKIE = 'KEEP_LOGIN';

    protected static $cookies = [
        self::COOKIE => 'cookie',
        self::COOKIE . '_FACEBOOK' => 'cookie_facebook',
        self::COOKIE . '_TWITTER' => 'cookie_twitter',
        self::COOKIE . '_GOOGLE' => 'cookie_google',
        self::COOKIE . '_VK' => 'cookie_vk',
    ];

    /** @var bool */
    protected static $isPi;

    public static function isPi()
    {
        if (!isset(self::$isPi)) {
            self::$isPi = version_compare(Settings::get('chevereto_version_installed'), '3.14.0.beta.1', '>=');
        }

        return self::$isPi;
    }

    public static function getSocialCookieName($name)
    {
        $flip = array_flip(self::$cookies);
        return $flip['cookie_' . $name];
    }

    public static function tryLogin()
    {
        if (self::isPi()) {
            self::tryCookies();
        } else {
            try {
                $login = false;
                if (isset($_COOKIE['KEEP_LOGIN'])) {
                    $login = self::loginCookiePrePi('internal');
                } elseif (isset($_COOKIE['KEEP_LOGIN_SOCIAL'])) {
                    $login = self::loginCookiePrePi('social');
                }
                if ($login == false && isset($_SESSION['login'])) {
                    $login = self::login($_SESSION['login']['id']);
                }
            } catch (Exception $e) {
                self::logoutPrePi();
                throw new Exception($e->getMessage(), $e->getCode());
            }
        }
    }

    /**
     * @return null|array|false Null if no cookies, array if cookie+login, false if cookie+error
     */
    public static function tryCookies()
    {
        if (array_key_exists('KEEP_LOGIN_SOCIAL', $_COOKIE)) {
            self::updateSocialCookie();
        }
        $login = null;
        foreach (self::$cookies as $cookieName => $type) {
            if (!array_key_exists($cookieName, $_COOKIE)) {
                continue;
            }
            $loginCookie = self::loginCookie($cookieName);
            if ($loginCookie) {
                $login = $loginCookie;
                continue;
            }
        }

        return $login;
    }

    /**
     * @return array|false logged user if any
     */
    protected static function loginCookie($cookieName = self::COOKIE)
    {
        if (!array_key_exists($cookieName, self::$cookies)) {
            return;
        }
        $validate = self::validateCookie($cookieName);
        if ($validate['valid']) {
            self::login($validate['user_id'], $validate['cookie']['type']);
            self::$session['id'] = $validate['login_id'];
            self::$session['login_cookies'][] = $validate['login_id'];

            return self::$logged_user;
        } else {
            Requestlog::insert(array('result' => 'fail', 'type' => 'login', 'user_id' => $validate['user_id']));
            static::unsetCookie($cookieName);

            return false;
        }
    }

    /**
     * Login the target user $id
     *
     * Set and return array self::$logged_user
     */
    public static function login($id, $cookieType = 'cookie')
    {
        $flip = array_flip(self::$cookies);
        if (!array_key_exists($cookieType, $flip)) {
            throw new Exception(sprintf('Invalid login $by %s', $cookieType));
        }
        $user = User::getSingle($id, 'id');
        if ($user) {
            foreach (['albums', 'images'] as $t) {
                $s = 'guest_' . $t;
                if (!is_array($_SESSION[$s] ?? null)) {
                    continue;
                }
                try {
                    $db = DB::getInstance();
                    $todoTable = DB::getTable($t); // images
                    $fieldPrefix = DB::getFieldPrefix($t); // image
                    $db->query('UPDATE ' . $todoTable . ' SET ' . $fieldPrefix . '_user_id=' . $id . ' WHERE ' . $fieldPrefix . '_id IN (' . implode(',', $_SESSION[$s]) . ')');
                    $db->exec();
                    if ($db->rowCount()) {
                        DB::increment('users', [$fieldPrefix . '_count' => '+' . $db->rowCount()], ['id' => $id]);
                    }
                } catch (Exception $e) {
                } // Silence
                unset($_SESSION[$s]);
            }
        }
        Requestlog::delete([
            'user_id' => $id,
            'result' => 'fail',
            'type' => 'login',
            'ip' => G\get_client_ip(),
        ]);
        if ($user['login']) {
            if ($user['status'] == 'valid') {
                self::unsetSignup();
                self::$session = [
                    'user_id' => $id,
                    'type' => $cookieType,
                ];
            } else {
                self::setSingup([
                    'status' => $user['status'],
                    'email' => $user['email'],
                ]);
            }
        }
        if (isset(self::getUser()['timezone']) && self::getUser()['timezone'] !== Settings::get('default_timezone') and G\is_valid_timezone($user['timezone'] ?? null)) {
            date_default_timezone_set($user['timezone']);
        }

        foreach (['image_count_label', 'album_count_label'] as $v) {
            $user[$v] = isset(self::$logged_user[$v]) ? _s(self::$logged_user[$v]) : '';
        }

        self::$logged_user = $user;

        return self::$logged_user;
    }

    /**
     * Logout and remove all cookies (including DB)
     * Beware when using this.
     */
    public static function logout()
    {
        if (!self::isPi()) {
            return self::logoutPrePi();
        }
        self::$logged_user = null;
        self::$session = null;
        self::unsetSignup();
        foreach (self::$cookies as $cookieName => $type) {
            $validate = self::validateCookie($cookieName);
            if ($validate['valid'] == null) {
                continue;
            }
            if ($validate['valid']) {
                self::delete(['id' => $validate['login_id']]);
            }
            static::unsetCookie($cookieName);
        }
    }

    public static function insert($values)
    {
        if (!is_array($values)) {
            throw new LoginException('Expecting array values, ' . gettype($values) . ' given in ' . __METHOD__, 100);
        }
        if (!isset($values['ip'])) {
            $values['ip'] = G\get_client_ip();
        }
        if (!isset($values['hostname'])) {
            $values['hostname'] = json_encode(array_merge(G\parse_user_agent($_SERVER['HTTP_USER_AGENT'])));
        }
        if (!isset($values['date'])) {
            $values['date'] = G\datetime();
        }
        if (!isset($values['date_gmt'])) {
            $values['date_gmt'] = G\datetimegmt();
        }
        if (G\starts_with('cookie', $values['type'])) {
            $cookieName = self::COOKIE;
            if ($values['type'] != 'cookie') {
                $cookieName .= '_' . G\str_replace_first('COOKIE_', '', strtoupper($values['type']));
            }
            $tokenize = generate_hashed_token($values['user_id']);
            $values['secret'] = $tokenize['hash'];
            $insert = DB::insert('logins', $values);
            if ($insert) {
                $d = \DateTime::createFromFormat('Y-m-d H:i:s', $values['date_gmt'], new \DateTimeZone('UTC'));
                $cookie = $tokenize['public_token_format'] . ':' . $d->getTimestamp();
                static::setCookie($cookieName, $cookie);
            }

            return $insert;
        } else {
            return DB::insert('logins', $values);
        }
    }

    public static function loginCookiePrePi($type = 'internal')
    {
        if (!in_array($type, ['internal', 'social'])) {
            return;
        }
        $cookie = $_COOKIE[$type == 'internal' ? 'KEEP_LOGIN' : 'KEEP_LOGIN_SOCIAL'];
        $explode = array_filter(explode(':', $cookie));
        // CHV: 0->id | 1:token | 2:timestamp
        // SOC: 0->id | 1:type | 2:hash | 3:timestamp
        $count = $type == 'social' ? 4 : 3;
        if (count($explode) !== $count) {
            return false;
        }
        foreach ($explode as $exp) {
            if ($exp == null) {
                return false;
            }
        }
        $user_id = decodeID($explode[0]);
        $login_db_arr = [
            'user_id' => $user_id,
            'type' => $type == 'internal' ? 'cookie' : $explode[1],
            'date_gmt' => gmdate('Y-m-d H:i:s', end($explode)),
        ];
        $login_db = self::get($login_db_arr, null, 1);
        $is_valid_token = $type == 'internal' ? check_hashed_token($login_db['secret'], $cookie) : password_verify($login_db['secret'] . $login_db['token_hash'], $explode[2]);
        if ($is_valid_token) {
            return self::login($login_db['user_id'], $type == 'internal' ? 'cookie' : $explode[1]);
        } else {
            Requestlog::insert(array('result' => 'fail', 'type' => 'login', 'user_id' => $user_id));
            self::logoutPrePi();

            return null;
        }
    }

    public static function logoutPrePi()
    {
        self::$logged_user = null;
        $doing = $_SESSION['login']['type'];

        if ($doing == 'session') {
            self::delete([
                'user_id' => $_SESSION['login']['id'],
                'type' => 'session',
                'date_gmt' => $_SESSION['login']['datetime'],
            ]);
        }

        session_unset();
        // session_destroy();

        // Unset the cookie from client and DB
        $cookies = ['KEEP_LOGIN', 'KEEP_LOGIN_SOCIAL'];
        foreach ($cookies as $cookie_name) {
            static::unsetCookie($cookie_name);
            if ($cookie_name == 'KEEP_LOGIN_SOCIAL') {
                continue;
            }
            $cookie = $_COOKIE[$cookie_name];
            $explode = array_filter(explode(':', $cookie));
            if (count($explode) == 4) {
                foreach ($explode as $exp) {
                    if ($exp == null) {
                        return false;
                    }
                }
                $user_id = decodeID($explode[0]);
                self::delete([
                    'user_id' => $user_id,
                    'type' => 'cookie',
                    'date_gmt' => gmdate('Y-m-d H:i:s', $explode[3]),
                ]);
            }
        }
    }

    public static function updateSocialCookie()
    {
        $cookieName = self::COOKIE . '_SOCIAL';
        $cookie = $_COOKIE[$cookieName];
        // SOC: 0->user_id | 1:type | 2:hash | 3:timestamp
        $explode = array_filter(explode(':', $cookie));
        if (count($explode) != 4) {
            self::unsetCookie($cookieName);
            return;
        }
        $user_id = decodeID($explode[0]);
        $type = $explode[1];

        if (!in_array($type, self::getSocialServices(['flat' => true]))) {
            self::unsetCookie($cookieName);
            return;
        }
        $hash = $explode[2];
        $login_arr = [
            'user_id' => $user_id,
            'type' => $type,
            'date_gmt' => gmdate('Y-m-d H:i:s', $explode[3]),
        ];
        $login = self::get($login_arr, null, 1);
        $is_valid_token = password_verify($login['secret'] . $login['token_hash'], $hash);
        if ($is_valid_token) {
            unset($login_arr['date_gmt']);
            $login_arr['type'] = 'cookie_' . $type;
            self::insert($login_arr);
        }
        self::unsetCookie($cookieName);
    }

    public static function get($values, $sort = array(), $limit = null)
    {
        $login_db = DB::get('logins', $values, 'AND', $sort, $limit);
        return DB::formatRows($login_db);
    }

    public static function hasSingup()
    {
        return isset($_SESSION['signup']);
    }

    public static function getSingup()
    {
        return $_SESSION['signup'];
    }

    public static function setSingup($var)
    {
        $_SESSION['signup'] = $var;
    }

    public static function unsetSignup()
    {
        unset($_SESSION['signup']);
    }

    public static function hasSession()
    {
        return isset(self::$session);
    }

    public static function getSession()
    {
        return self::$session;
    }

    public static function getUser()
    {
        return self::isLoggedUser() ? self::$logged_user : null;
    }

    public static function setUser($key, $value)
    {
        if (self::$logged_user) {
            self::$logged_user[$key] = $value;
        }
    }

    public static function isLoggedUser()
    {
        return !is_null(self::$logged_user);
    }

    /**
     * @return array
     */
    public static function validateCookie($cookieName)
    {
        if (!isset($_COOKIE[$cookieName])) {
            return [
                'valid' => null,
            ];
        }
        $getCookie = static::getCookie($cookieName);
        $login_arr = $getCookie;
        unset($login_arr['raw']);
        $login = self::get($login_arr, null, 1);
        $is_valid = check_hashed_token($login['secret'] ?? '', $getCookie['raw']);

        return [
            'valid' => $is_valid,
            'cookie' => $getCookie,
            'login_id' => $login['id'] ?? '',
            'user_id' => $getCookie['user_id']
        ];
    }

    public static function checkPassword($id, $password)
    {
        $login = self::get(['user_id' => $id, 'type' => 'password'], null, 1);
        return password_verify($password, $login['secret']);
    }

    public static function update($id, $values)
    {
        return DB::update('logins', $values, ['id' => $id]);
    }

    protected static function setCookie($key, $value)
    {
        $args = [
            $key, $value, time() + (60 * 60 * 24 * 30),
        ];
        return static::cookie(...$args);
    }

    public static function unsetCookie($key)
    {
        return static::cookie($key, '', -1);
    }

    protected static function cookie($key, $value, $time)
    {
        if ($time == -1) {
            unset($_COOKIE[$key]);
        } else {
            $_COOKIE[$key] = $value;
        }
        $args = func_get_args();
        $args[] = G_ROOT_PATH_RELATIVE;
        if ($time == -1) {
            // PrePi
            setcookie(...$args);
        }
        $args[] = G\get_host_domain();
        $args[] = G_HTTP_PROTOCOL == 'https';
        $args[] = true;
        // setcookie(name,value,expire,path,domain,secure,httponly)
        return setcookie(...$args);
    }

    public static function addPassword($id, $password, $update_session = true)
    {
        return self::passwordDB('insert', $id, $password, $update_session);
    }

    public static function changePassword($id, $password, $update_session = true)
    {
        return self::passwordDB('update', $id, $password, $update_session);
    }

    public static function getCookie($cookieName)
    {
        $rawCookie = $_COOKIE[$cookieName];
        $explode = explode(':', $rawCookie);
        if (count($explode) !== 3) {
            return [];
            // throw new Exception('Invalid raw cookie format');
        }
        foreach ($explode as $exp) {
            if ($exp == null) {
                return [];
                // throw new Exception('Invalid raw cookie format');
            }
        }
        $return = [
            'raw' => $rawCookie,
            'user_id' => decodeID($explode[0]),
            'type' => self::$cookies[$cookieName],
            'date_gmt' => gmdate('Y-m-d H:i:s', $explode[2])
        ];

        return $return;
    }

    protected static function passwordDB($action, $id, $password, $update_session)
    {
        $action = strtoupper($action);

        if (!in_array($action, array('UPDATE', 'INSERT'))) {
            throw new LoginException('Expecting UPDATE or INSERT statements in ' . __METHOD__, 200);
        }

        $hash = password_hash($password, PASSWORD_BCRYPT);

        $array_values = array(
            'ip' => G\get_client_ip(),
            'date' => G\datetime(),
            'date_gmt' => G\datetimegmt(),
            'secret' => $hash,
        );

        if ($action == 'UPDATE') {
            $dbase = DB::update('logins', $array_values, array('type' => 'password', 'user_id' => $id));
        } else {
            $array_values['user_id'] = $id;
            $array_values['type'] = 'password';
            $dbase = DB::insert('logins', $array_values);
        }

        // Update logged user?
        if (self::getUser()['id'] == $id and self::$session and $update_session) {
            self::$session = [
                'id' => $id,
                'type' => 'password',
            ];
        }

        return $dbase;
    }

    public static function delete($values, $clause = 'AND')
    {
        return DB::delete('logins', $values, $clause);
    }

    public static function getSocialServices($args = [])
    {
        $args = array_merge([
            'get' => 'all',
            'flat' => false,
        ], $args);

        $return = [];

        if ($args['get'] == 'all') {
            if ($args['flat'] === true) {
                $return = array_keys(self::$social_services);
            } else {
                $return = self::$social_services;
            }
        } else {
            foreach (self::$social_services as $k => $v) {
                if (($args['get'] == 'enabled' and !getSetting($k)) or ($args['get'] == 'disabled' and getSetting($k))) {
                    continue;
                }
                if ($args['flat'] === true) {
                    $return[] = $k;
                } else {
                    $return[$k] = $v;
                }
            }
        }

        return $return;
    }

    public static function isAdmin()
    {
        if(!isset(self::$logged_user)) {
            return false;
        }
        return (bool) self::$logged_user['is_admin'];
    }

    public static function isManager()
    {
        if(!isset(self::$logged_user)) {
            return false;
        }
        return (bool) self::$logged_user['is_manager'];
    }
}

class LoginException extends Exception
{
}
