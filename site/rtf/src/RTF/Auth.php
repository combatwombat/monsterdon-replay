<?php

namespace RTF;

class Auth extends Base {

    private $method;
    private $currentUser;
    private $redirectIfLoggedOut;

    public function __construct($container, $authMethod = "session", $redirectIfLoggedOut = null) {
        $this->container = $container;
        $this->method = $authMethod;
        $this->redirectIfLoggedOut = $redirectIfLoggedOut;

    }

    public function __invoke() {
        session_start();

        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }

        header("Cache-Control: private");

        return $this->exitIfNoAccess();
    }

    public function exitIfNoAccess() {


        if (!$this->hasAccess()) {

            // htmx request?
            if (isset($_SERVER['HTTP_HX_REQUEST'])) {
                http_response_code(401);
                if ($this->container->view->templateExists("components/401")) {
                    $this->container->view("components/401");
                } else {
                    echo "You are not logged in";
                }
                die();
            } else {
                if ($this->redirectIfLoggedOut) {
                    // remember original route
                    $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'];
                    $this->container->router->redirect($this->redirectIfLoggedOut, 302);
                } else {

                    if ($this->method == 'http') {
                        header('WWW-Authenticate: Basic');
                        header('HTTP/1.0 401 Unauthorized');
                        echo "nope";
                        sleep(1); // to annoy bots a bit
                    } else {
                        http_response_code(401);
                    }

                    die();

                }
            }
        }

    }

    public function hasAccess() {
        return $this->isLoggedIn(); // here be the place for user roles etc.
    }

    public function isLoggedIn() {

        if (session_status() == PHP_SESSION_NONE) {
            session_start();
        }

        switch ($this->method) {
            case 'http':
                $res = $this->checkHTTPLogin();
                return $res;

            case 'session':

                $dbUser = $this->getDBUserByCookie(); // get db user by session or remember_me cookie

                if (!empty($dbUser)) {

                    $permissionsDB = $this->container->db->fetchAll("SELECT name FROM permissions WHERE id IN (SELECT permission_id FROM permissions_roles WHERE role_id = :role_id)", ['role_id' => $dbUser['role_id']]);
                    $permissions = [];
                    if ($permissionsDB) {
                        foreach ($permissionsDB as $permission) {
                            $permissions[] = $permission['name'];
                        }
                    }

                    $this->currentUser = [
                        "type" => "db",
                        "id" => $dbUser['id'],
                        'permissions' => $permissions,
                        "email" => $dbUser['email'],
                        "password_hash" => $dbUser['password_hash'],
                        "displayName" => ucfirst(explode("@", $dbUser['email'])[0])
                    ];

                    return true;
                }

        }
        return false;
    }

    /**
     * Check if current user has a certain permission
     * @param $permission string
     * @return bool
     */
    public function currentUserCan($permission) {
        if ($this->currentUser && isset($this->currentUser['permissions']) && in_array($permission, $this->currentUser['permissions'])) {
            return true;
        }
        return false;
    }


    /**
     * get cookie token, decrypt it, get matching user from db
     * remember cookie: {user_id, remember_token}
     * @return array db user array
     */
    public function getDBUserByCookie() {
        if (isset($_SESSION['user_id']) && $_SESSION['user_id']) {
            $user = $this->db->getById('users', $_SESSION['user_id']);

            // remember_token empty or expired? unset remember cookie
            if (empty($user['remember_token']) || new \DateTime() > new \DateTime($user['remember_token_expires_at'])) {
                setcookie("remember", "", [
                    'expires' => time() - 3600,
                    'path' => '/',
                    'domain' => '.' . $this->config("domain"),
                    'secure' => true,
                    'httponly' => true,
                    'samesite' => 'Strict'
                ]);
            }

            return $user;
        }


        if (!isset($_COOKIE['remember'])) {
            return [];
        }

        $tokenCookie = $this->decryptRememberToken($_COOKIE['remember']);
        if (!$tokenCookie) {
            return [];
        }

        $dbUser = $this->db->getById("users", $tokenCookie['user_id']);

        if ($dbUser && $dbUser['remember_token'] == $tokenCookie['remember_token']) {
            $_SESSION['user_id'] = $dbUser['id'];


            return $dbUser;
        }
        return [];
    }

    /**
     * Decrypt remember token
     * @param $dataString
     * @return mixed
     */
    public function decryptRememberToken($dataString) {
        $key = $this->config("auth.session.rememberMeKey");
        $iv = substr(hash('sha256', $key), 0, 16);
        $dataJSON = openssl_decrypt($dataString, 'aes-256-cbc', $key, 0, $iv);
        $data = json_decode($dataJSON, true);
        return $data;
    }

    /**
     * Encrypt remember token, containing {user_id, remember_token}
     * @param $data
     * @return false|string
     */
    public function encryptRememberToken($data) {
        $key = $this->config("auth.session.rememberMeKey");
        $iv = substr(hash('sha256', $key), 0, 16);
        $dataJSON = json_encode($data);
        return openssl_encrypt($dataJSON, 'aes-256-cbc', $key, 0, $iv);

    }

    public function createSession($userId, $rememberMe = true) {
        $_SESSION['user_id'] = $userId;

        if ($rememberMe) {
            $this->setOrRenewRememberToken($userId);
        }

        return true;
    }

    /**
     * set remember_token cookie, containing encrypted {user_id,remember_token}
     * @return bool success
     */
    public function setOrRenewRememberToken($userId) {

        $dbUser = $this->db->getById("users", $userId);

        if (empty($dbUser)) {
            return false;
        }

        // remember_token_expires at older than half its lifetime?
        $createNewRememberToken = false;
        $token = $dbUser['remember_token'];

        $tokenExpiresAt = new \DateTime();
        $tokenExpiresAt->add(new \DateInterval("P{$this->config("auth.session.rememberMeExpiresDays")}D"));
        $tokenExpiresAtTimestamp = $tokenExpiresAt->getTimestamp();

        if (empty($token)) {
            $createNewRememberToken = true;
        } else {
            $now = new \DateTime();
            $expiresAt = $dbUser['remember_token_expires_at'];

            if (empty($expiresAt)) {
                $createNewRememberToken = true;
            } else {
                $expiresAtDateTime = new \DateTime($expiresAt);
                $interval = $now->diff($expiresAtDateTime);
                if ($interval->days < $this->config("auth.session.rememberMeExpiresDays") / 2) {

                    // token and expires at exists, just expire_at is too old -> just update expires_at
                    $res = $this->db->update('users', ['remember_token_expires_at' => $tokenExpiresAt->format("Y-m-d H:i:s")], ['id' => $userId]);

                }
            }
        }

        if ($createNewRememberToken) {
            $token = sha1(uniqid('', true) . $userId);
            $res = $this->db->update('users', ['remember_token' => $token, 'remember_token_expires_at' => $tokenExpiresAt->format("Y-m-d H:i:s")], ['id' => $userId]);
        }



        $data = [
            'salt' => sha1(uniqid('', true) . $userId),
            'user_id' => $userId,
            'remember_token' => $token
        ];

        // set remember_me cookie
        setcookie("remember", $this->encryptRememberToken($data), [
            'expires' => $tokenExpiresAtTimestamp,
            'path' => '/',
            'domain' => '.' . $this->config("domain"),
            'secure' => true,
            'httponly' => true,
            'samesite' => 'Strict'
        ]);



    }


    public function isCSRFTokenValid() {
        return isset($_POST['csrf_token']) && $_POST['csrf_token'] == $_SESSION['csrf_token'];
    }


    /**
     * Check if user can be authenticated via HTTP auth (or has active simple session)
     * @return bool true if user/pass match one in config.php
     */
    public function checkHTTPLogin() {

        if (isset($_SESSION['currentUser'])) {
            return true;
        }

        if (isset($_SERVER['PHP_AUTH_USER']) && isset($_SERVER['PHP_AUTH_PW'])) {
            $users = $this->container->config("auth.http.users");
            $userMatches = false;
            $currentUserName = null;
            if (!empty($users)) {
                foreach ($users as $user) {
                    if ($user['user'] == $_SERVER['PHP_AUTH_USER'] && $user['pass'] == $_SERVER['PHP_AUTH_PW']) {
                        $currentUserName = $user['user'];
                        $userMatches = true;
                        break;
                    }
                }
            }
            if ($userMatches) {
                $_SESSION['currentUser'] = $this->currentUser = [
                    "type" => "config",
                    "user" => $currentUserName,
                    "displayName" => ucfirst($currentUserName)
                ];
                return true;
            }

        }

      return false;

    }


    public function login($usernameOrEmail, $password, $redirectAfterLogin = false, $method = 'session', $rememberMe = true) {
        $success = false;
        switch ($method) {
            case 'session':
                $success = $this->loginSession($usernameOrEmail, $password, $rememberMe);

                break;
            case 'token':
                $success = $this->loginToken($usernameOrEmail, $password);
                break;
        }

        if ($success && isset($_SESSION['redirect_after_login']) && $_SESSION['redirect_after_login'] && $redirectAfterLogin) {
            $location = $_SESSION['redirect_after_login'];
            $_SESSION['redirect_after_login'] = '';
            $this->container->router->redirect($location);
        }

        return $success;
    }

    public function loginSession($email, $password, $rememberMe) {

        // get user from db
        $user = $this->container->db->getByEmail('users', $email);
        if ($user) {
            if (password_verify($password, $user['password_hash'])) {

               return $this->createSession($user['id'], $rememberMe);
            }
        }

        return false;
    }

    /**
     * Generate token on successful login.
     * Token = JSON with
     * - user id
     * - expires timestamp
     * Signed with config/auth/token/private_key
     * A bit like JWT, but simpler.
     * @param $username
     * @param $password
     *
     * @return mixed token or false
     */
    public function loginToken($username, $password) {

        $user = $this->container->db->getByUsername('users', $username);
        if ($user) {
            if (password_verify($password, $user['password_hash'])) {
                return $this->createToken($user['id']);
            }
        }
    }

    public function logout() {

        session_destroy();

        // delete remember cookie
        setcookie("remember", "", [
            'expires' => time() - 3600,
            'path' => '/',
            'domain' => '.' . $this->config("domain"),
            'secure' => true,
            'httponly' => true,
            'samesite' => 'Strict'
        ]);

    }




    /**
     * Create new user in DB
     * @param $email
     * @param $password
     * @return user id
     */
    public function createDBUser($email, $password, $role = 1) {

        $email = trim($email);

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new \Exception("Email is invalid");
        }

        $user = $this->container->db->getByEmail('users', $email);
        if ($user) {
            throw new \Exception("User exists already");
        }

        if (strlen($password) < 8) {
            throw new \Exception("Password is too short");
        }

        $res = $this->container->db->insert("users", ["email" => $email, "password_hash" => password_hash($password, PASSWORD_DEFAULT), 'role_id' => $role]);
        if (!$res) {
            throw new \Exception("User couldn't be created");
        }

        return $res;
    }

    /**
     * @return array|null currently logged in user
     */
    public function getCurrentUser() {
        return $this->currentUser;
    }

    public function createToken($userId) {
        $data = [
            'user_id' => $userId,
            'expires' => time() + $this->config("auth.token.expires") // expires in 1 month
        ];
    }

    public function encodeToken($data) {
        $json = json_encode($data);
        $signature = $this->getTokenSignature($json);
        return base64_encode($json . "." . $signature);
    }

    public function getTokenSignature($data) {
        return empty($this->config("auth.token.key")) ? false: hash_hmac("sha3-512", $data, $this->config("auth.token.key"));
    }
}