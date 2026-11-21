<?php

declare(strict_types=1);

/**
 * The goal of this file is to allow developers a location
 * where they can overwrite core procedural functions and
 * replace them with their own. This file is loaded during
 * the bootstrap process and is called during the framework's
 * execution.
 *
 * This can be looked at as a `master helper` file that is
 * loaded early on, and may also contain additional functions
 * that you'd like to use throughout your entire application
 *
 * @see: https://codeigniter.com/user_guide/extending/common.html
 */

if (! function_exists('same_origin_url')) {
    function same_origin_url(string $url): string
    {
        $parts = parse_url($url);
        if ($parts === false) {
            return '/';
        }

        $path = isset($parts['path']) && is_string($parts['path']) ? $parts['path'] : '/';
        $query = isset($parts['query']) && is_string($parts['query']) ? '?' . $parts['query'] : '';

        return $path . $query;
    }
}
