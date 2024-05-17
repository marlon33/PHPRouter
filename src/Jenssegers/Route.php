<?php

namespace PHPRouter;

class Route
{
    public static function get(string $route, $action): void
    {
        self::register('GET', $route, $action);
    }

    public static function post(string $route, $action): void
    {
        self::register('POST', $route, $action);
    }

    public static function put(string $route, $action): void
    {
        self::register('PUT', $route, $action);
    }

    public static function delete(string $route, $action): void
    {
        self::register('DELETE', $route, $action);
    }

    public static function any(string $route, $action): void
    {
        self::register('*', $route, $action);
    }

    public static function secure(string $method, string $route, $action): void
    {
        if (!Router::secure()) {
            return;
        }

        self::register($method, $route, $action);
    }

    public static function controller(array $controllers, string $defaults = 'index'): void
    {
        Router::controller($controllers, $defaults);
    }

    public static function register($method, $route, $action): void
    {
        if (is_array($method)) {
            foreach ($method as $http) {
                Router::route($http, $route, $action);
            }
            return;
        }

        foreach ((array) $route as $uri) {
            Router::route($method, $uri, $action);
        }
    }
}
