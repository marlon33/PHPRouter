<?php

namespace PHPRouter;

class Router
{
    public static ?string $uri = null;
    public static ?string $base = null;
    private static bool $routed = false;

    public static function uri(): string
    {
        if (!is_null(self::$uri)) {
            return self::$uri;
        }

        if (isset($_SERVER['REQUEST_URI'], $_SERVER['SCRIPT_NAME'])) {
            self::$uri = $_SERVER['REQUEST_URI'];

            if (strpos(self::$uri, $_SERVER['SCRIPT_NAME']) === 0) {
                self::$uri = substr(self::$uri, strlen($_SERVER['SCRIPT_NAME']));
            } elseif (strpos(self::$uri, dirname($_SERVER['SCRIPT_NAME'])) === 0) {
                self::$uri = substr(self::$uri, strlen(dirname($_SERVER['SCRIPT_NAME'])));
            }

            if (($pos = strpos(self::$uri, '?')) !== false) {
                self::$uri = substr(self::$uri, 0, $pos);
            }
        } elseif (isset($_SERVER['PATH_INFO'])) {
            self::$uri = $_SERVER['PATH_INFO'];
        }

        self::$uri = trim(self::$uri, '/');

        if (self::$uri === '') {
            self::$uri = '/';
        }

        return self::$uri;
    }

    public static function base(string $uri = ''): string
    {
        if (!is_null(self::$base)) {
            return self::$base . $uri;
        }

        if (isset($_SERVER['HTTP_HOST'])) {
            self::$base = self::secure() ? 'https' : 'http';
            self::$base .= '://' . $_SERVER['HTTP_HOST'];
            self::$base .= str_replace(basename($_SERVER['SCRIPT_NAME']), '', $_SERVER['SCRIPT_NAME']);
        } else {
            self::$base = 'http://localhost/';
        }

        return self::$base . $uri;
    }

    public static function secure(): bool
    {
        return !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
    }

    public static function method(): string
    {
        return strtoupper($_SERVER['REQUEST_METHOD']);
    }

    public static function route(string $method, string $route, $action): void
    {
        if (self::$routed) {
            return;
        }

        if ($method !== '*' && strtoupper($method) !== self::method()) {
            return;
        }

        $route = trim($route, '/');

        if ($route === '') {
            $route = '/';
        }

        if ($route === self::uri()) {
            self::call($action);
            return;
        }

        if (strpos($route, '(') !== false) {
            $patterns = [
                '(:num)' => '([0-9]+)',
                '(:any)' => '([a-zA-Z0-9\.\-_%=]+)',
                '(:all)' => '(.*)',
                '/(:num?)' => '(?:/([0-9]+))?',
                '/(:any?)' => '(?:/([a-zA-Z0-9\.\-_%=]+))?',
                '/(:all?)' => '(?:/(.*))?',
            ];

            $route = str_replace(array_keys($patterns), array_values($patterns), $route);

            if (preg_match('#^' . $route . '$#', self::uri(), $parameters)) {
                self::call($action, array_slice($parameters, 1));
                return;
            }
        }
    }

    private static function call($action, array $parameters = []): void
    {
        if (is_callable($action)) {
            echo call_user_func_array($action, $parameters);
        } elseif (is_string($action) && strpos($action, '@')) {
            [$controller, $method] = explode('@', $action);
            $class = basename($controller);

            if (strpos($method, '(:') !== false) {
                foreach ($parameters as $key => $value) {
                    $method = str_replace('(:' . ($key + 1) . ')', $value, $method, $count);
                    if ($count > 0) {
                        unset($parameters[$key]);
                    }
                }
            }

            if (!$method) {
                $method = 'index';
            }

            if (!class_exists($class)) {
                $controllerPath = "controllers/$controller.php";
                if (file_exists($controllerPath)) {
                    include $controllerPath;
                }
            }

            if (!class_exists($class)) {
                return;
            }

            $instance = new $class();
            echo call_user_func_array([$instance, $method], $parameters);
        }

        self::$routed = true;
    }

    public static function controller(array $controllers, string $defaults = 'index'): void
    {
        foreach ($controllers as $controller) {
            if (strpos(strtolower(self::uri()), strtolower($controller)) === 0) {
                $controller = str_replace('.', '/', $controller);

                $wildcards = str_repeat('/(:any?)', 6);
                $pattern = trim($controller . $wildcards, '/');

                self::route('*', $pattern, "$controller@(:1)");
            }
        }
    }
}
