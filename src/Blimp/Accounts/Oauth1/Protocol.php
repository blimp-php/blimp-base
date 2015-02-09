<?php
namespace Blimp\Accounts\Oauth1;

use Symfony\Component\HttpFoundation\JsonResponse;

class Protocol {
    public static function get($url, $params, array $oauth_params, $consumerSecret, $oauthToken, array $headers = []) {
        return self::exec('GET', $url, $params, $oauth_params, $consumerSecret, $oauthToken, $headers, null);
    }

    public static function post($url, $params, array $oauth_params, $consumerSecret, $oauthToken = '', array $headers = [], $body = null) {
        return self::exec('POST', $url, $params, $oauth_params, $consumerSecret, $oauthToken, $headers, $body);
    }

    private static function exec($method, $url, $params, array $oauth_params, $consumerSecret, $oauthToken = '', array $headers = [], $body = null) {
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

        $all_params = array_merge($query_params, $params, $oauth_params);

        uksort($all_params, 'strcmp');

        $fields = [];
        $oauth_fields = [];
        $signature_fields = [];

        foreach ($all_params as $key => $val) {
            if ($val !== null) {
                $escaped_key = \rawurlencode($key);
                $escaped_value = \rawurlencode($val);

                if (strncmp($key, 'oauth_', 6) == 0 || strncmp($key, 'xoauth_', 6) == 0) {
                    $oauth_fields[$key] = $key . '="' . $escaped_value . '"';
                } else {
                    $fields[$escaped_key] = $escaped_key . '=' . $escaped_value;
                }

                $signature_fields[$escaped_key] = $escaped_key . '=' . $escaped_value;
            }
        }

        $base_signature = $method . '&' . \rawurlencode($clean_url) . '&';
        $base_signature .= \rawurlencode(implode('&', $signature_fields));

        $hashed_signature = self::hmac_sha1($base_signature, $consumerSecret, $oauthToken);
        $encoded_hashed_signature = base64_encode($hashed_signature);
        $escaped_encoded_hashed_signature = \rawurlencode($encoded_hashed_signature);

        $oauth_fields['oauth_signature'] = 'oauth_signature="' . $escaped_encoded_hashed_signature . '"';
        uksort($oauth_fields, 'strcmp');

        $auth_header = 'Authorization: OAuth ' . implode(', ', $oauth_fields);

        $headers[] = $auth_header;
        $headers[] = 'Expect:';

        if (count($fields) > 0) {
            if ($method == 'GET') {
                $url = $clean_url . '?' . implode('&', $fields);
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
        }

        \curl_setopt($curl, CURLOPT_NOSIGNAL, true);
        \curl_setopt($curl, CURLOPT_NOPROGRESS, true);
        \curl_setopt($curl, CURLOPT_AUTOREFERER, true);
        \curl_setopt($curl, CURLOPT_FOLLOWLOCATION, true);
        \curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
        \curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);

        \curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);

        $result = \curl_exec($curl);

        if ($result) {
            $toReturn = self::parseResponse($curl, $result, $params);

            \curl_close($curl);

            return $toReturn;
        } else {
            \curl_close($curl);

            throw new KRestException(result, errorBuffer);
        }
    }

    public static function getNonceAndTimestamp() {
        $ts = time();
        $n = sha1(uniqid('', true) . mt_rand());

        return ['nonce' => $n, 'timestamp' => $ts];
    }

    private static function hmac_sha1($signature, $consumerSecret, $oauthToken) {
        $secretSigningKey = $consumerSecret . '&' . $oauthToken;

        return hash_hmac('sha1', $signature, $secretSigningKey, true);
    }

    private static function rsa_sha1($signature, $consumerSecret, $oauthToken) {
        $privateKey = openssl_pkey_get_private(
            file_get_contents($consumerSecret),
            $oauthToken
        );

        $signed = false;
        openssl_sign($signature, $signed, $privateKey);
        openssl_free_key($privateKey);

        return $signature;
    }

    private static function plaintext($signature, $consumerSecret, $oauthToken) {
        return $signature;
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