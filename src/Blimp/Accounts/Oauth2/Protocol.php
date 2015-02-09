<?php
namespace Blimp\Accounts\Oauth2;

use Symfony\Component\HttpFoundation\JsonResponse;

class Protocol {
    public static function get($url, array $params = [], array $headers = []) {
        return self::exec('GET', $url, $params, $headers, null);
    }

    public static function post($url, array $params = [], array $headers = [], $body = null) {
        return self::exec('POST', $url, $params, $headers, $body);
    }

    private static function exec($method, $url, array $params = [], array $headers = [], $body = null) {
        $clean_url = $url;

        $query_params = [];

        $url_parts = \parse_url($url);
        if (array_key_exists('query', $url_parts)) {
            $query = $url_parts['query'];
            unset($url_parts['query']);
            unset($url_parts['fragment']);

            $clean_url = \http_build_url('', $url_parts);

            \parse_str($query, $query_params);
        }

        if ($params === null) {
            $params = [];
        }

        $all_params = array_merge($query_params, $params);

        if ($all_params['access_token'] != null) {
            $headers[] = 'Authorization: Bearer ' . $all_params['access_token'];
            unset($all_params['access_token']);
        }

        if (count($all_params) > 0) {
            if ($method == 'GET') {
                $url = $clean_url . '?' . http_build_query($all_params);
            } else if ($method == 'POST') {
                if ($body !== null) {
                    $headers[] = 'Content-Type: application/json; charset=UTF-8';
                    if (!\is_string($body)) {
                        $body = json_encode($body);
                    }
                } else {
                    $headers[] = 'Content-Type: application/x-www-form-urlencoded; charset=UTF-8';
                    $body = http_build_query($params);
                }
            }
        }

        $curl = \curl_init();

        \curl_setopt($curl, CURLOPT_VERBOSE, true);

        \curl_setopt($curl, CURLOPT_URL, $url);
        \curl_setopt($curl, CURLOPT_HEADER, 0);

        \curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);

        if ($method == 'GET') {
            \curl_setopt($curl, CURLOPT_HTTPGET, true);
        } else if ($method == 'POST') {
            \curl_setopt($curl, CURLOPT_POST, true);
            \curl_setopt($curl, CURLOPT_POSTFIELDS, $body);
        } else {
            \curl_setopt($curl, CURLOPT_CUSTOMREQUEST, $method);
            \curl_setopt($curl, CURLOPT_POSTFIELDS, $body);
        }

        \curl_setopt($curl, CURLOPT_NOSIGNAL, true);
        \curl_setopt($curl, CURLOPT_NOPROGRESS, true);
        \curl_setopt($curl, CURLOPT_AUTOREFERER, true);
        \curl_setopt($curl, CURLOPT_FOLLOWLOCATION, true);
        \curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
        \curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);

        \curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);

        $result = \curl_exec($curl);

        if ($result !== false) {
            $toReturn = self::parseResponse($curl, $result, $params);

            \curl_close($curl);

            return $toReturn;
        } else {
            \curl_close($curl);

            throw new KRestException(result, errorBuffer);
        }
    }

    private static function parseResponse($curl, $buffer, array $params) {
        $content_length = \curl_getinfo($curl, CURLINFO_CONTENT_LENGTH_DOWNLOAD);

        $content_type = \curl_getinfo($curl, CURLINFO_CONTENT_TYPE);

        $data = [];

        if ($content_type != NULL && $content_length > 0) {
            if (!empty($content_type)) {
                if (strpos($content_type, 'application/json') === 0) {
                    $data = json_decode($buffer, true);
                } else if (strpos($content_type, 'application/xml') === 0) {
                    // TODO Parse XML
                } else if (strpos($content_type, 'application/x-www-form-urlencoded') === 0) {
                    parse_str($buffer, $data);
                } else {
                    if (substr($buffer, 0, 1) == '{') {
                        $data = json_decode($buffer, true);
                    } else {
                        parse_str($buffer, $data);
                    }
                }
            }
        }

        $httpCode = \curl_getinfo($curl, CURLINFO_RESPONSE_CODE);

        if ($httpCode == 200) {
            return $data;
        } else {
            $toReturn = new JsonResponse($data, $httpCode);
            return $toReturn;
        }
    }
}