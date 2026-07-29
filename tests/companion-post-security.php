<?php

namespace Typecho {
    class Widget
    {
        public $request;
        public $response;

        public static function widget($name)
        {
            if ($name === 'Widget_User') {
                return new \TestUser();
            }
            if ($name === 'Widget_Security') {
                return new \TestSecurity();
            }

            throw new \RuntimeException('Unexpected widget: ' . $name);
        }
    }

    class Db
    {
    }

    class Request
    {
        public static function getInstance()
        {
            return new self();
        }

        public function get($name, $default = null)
        {
            return $name === 'do' ? 'createPost' : $default;
        }
    }
}

namespace Widget {
    interface ActionInterface
    {
    }
}

namespace {
    class TestRequest
    {
        public function isPost()
        {
            return true;
        }
    }

    class TestResponse
    {
        public function setStatus($status)
        {
            if (empty($GLOBALS['icefox_security_protected'])) {
                throw new RuntimeException('POST response was emitted without CSRF protection');
            }
            if ($status !== 200) {
                throw new RuntimeException('Unexpected response status');
            }
        }

        public function setContentType($contentType)
        {
            if ($contentType !== 'application/json') {
                throw new RuntimeException('POST response must be JSON');
            }
        }
    }

    class TestSecurity
    {
        public function protect()
        {
            $GLOBALS['icefox_security_protected'] = true;
        }
    }

    class TestUser
    {
        public function hasLogin()
        {
            return false;
        }
    }

    define('__TYPECHO_ROOT_DIR__', dirname(__DIR__));
    require_once __DIR__ . '/../plugins/Icefox/Action.php';

    $reflection = new ReflectionClass('TypechoPlugin\\Icefox\\Action');
    $action = $reflection->newInstanceWithoutConstructor();
    $action->request = new TestRequest();
    $action->response = new TestResponse();
    $action->action();
}
