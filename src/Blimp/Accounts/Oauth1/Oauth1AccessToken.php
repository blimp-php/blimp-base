<?php
namespace Blimp\Accounts\Oauth1;

use Blimp\Base\BlimpException;
use Pimple\Container;
use Blimp\Accounts\Oauth1\Protocol;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

abstract class Oauth1AccessToken {
    protected $api;
    protected $request;

    /* endpoints */

    abstract public function getRequestTokenEndpoint();
    abstract public function getAuthenticateEndpoint();
    abstract public function getRequestAccessTokenEndpoint();

    /* application credentials */

    abstract public function getConsumerKey();
    abstract public function getConsumerSecret();

    /* access parameters */

    /*
     * The URL to redirect to after the user clicks a button in the third party dialog.
     *
     * This implementation uses request parameter 'redirect_uri'.
     * Can be overrided.
     */
    public function getRedirectURI() {
        return $this->request->query->get('redirect_uri') != NULL ? $this->request->query->get('redirect_uri') : ($this->request->query->get('state') != NULL ? $this->request->query->get('state') : '');
    }

    /*
     * Indicates if the user should be re-prompted for login and consent.
     *
     * This implementation uses request parameter 'force_login' and defaults do 'false'.
     * Can be overrided.
     */
    public function getForceLogin() {
        return $this->request->query->get('force_login') != NULL && ($this->request->query->get('force_login') == 'true' || $this->request->query->get('force_login') == '1');
    }


    /* return data */

    public function getOauthToken() {
        return $this->request->query->get('oauth_token') != NULL ? $this->request->query->get('oauth_token') : '';
    }

    public function getOauthVerifier() {
        return $this->request->query->get('oauth_verifier') != NULL ? $this->request->query->get('oauth_verifier') : '';
    }


    /* session data */

    public function getKey() {
        return $this->request->query->get('key') != NULL ? $this->request->query->get('key') : '';
    }

    public function recoverSessionData($key) {
        return $this->api['http.session']->remove($key);
    }

    public function saveSessionData($key, $value) {
        $this->api['http.session']->set($key, $value);
    }

    /* interaction */

    public function requestOAuthToken() {
        $key = Protocol::getNonceAndTimestamp();

        $redir = $this->request->getUriForPath($this->request->getPathInfo());

        $callback = $redir;
        $callback .= (strpos($redir, '?') === false ? '?' : '&') . 'key=' . $key['nonce'] . '&state=' . $this->getRedirectURI();

        $oauth_params = [
            'oauth_callback' => $callback,
            'oauth_consumer_key' => $this->getConsumerKey(),
            'oauth_nonce' => $key['nonce'],
            'oauth_signature_method' => 'HMAC-SHA1',
            'oauth_timestamp' => $key['timestamp'],
            'oauth_version' => '1.0'
        ];

        $oauth_data = Protocol::post($this->getRequestTokenEndpoint(), null, $oauth_params, $this->getConsumerSecret());

        if($oauth_data instanceof Response) {
            return $oauth_data;
        }

        if ($oauth_data['oauth_token'] != null) {
            $loc = $this->getAuthenticateEndpoint() . '?oauth_token=' . $oauth_data['oauth_token'];

            if ($this->getForceLogin()) {
                $loc .= '&force_login=true';
            }

            $this->saveSessionData($key['nonce'], $oauth_data['oauth_token_secret']);

            $response = new Response(Response::HTTP_TEMPORARY_REDIRECT);
            $response->headers->set('Location', $loc);

            return $response;
        }

        throw new BlimpException(Response::HTTP_INTERNAL_SERVER_ERROR, 'No data');
    }

    public function requestAccessToken($oauth_token, $oauth_verifier, $oauth_token_secret) {
        $key = Protocol::getNonceAndTimestamp();

        $oauth_params = [
            'oauth_consumer_key' => $this->getConsumerKey(),
            'oauth_nonce' => $key['nonce'],
            'oauth_signature_method' => 'HMAC-SHA1',
            'oauth_timestamp' => $key['timestamp'],
            'oauth_token' => $oauth_token,
            'oauth_verifier' => $oauth_verifier,
            'oauth_version' => '1.0'
        ] ;

        $oauth_data = Protocol::post($this->getRequestAccessTokenEndpoint(), null, $oauth_params, $this->getConsumerSecret(), '', null);

        return $oauth_data;
    }

    /* do stuff */

    abstract public function processAccountData(array $oauth_data);

    public function process(Request $request, Container $api) {
        $this->api = $api;
        $this->request = $request;

        switch ($this->request->getMethod()) {
            case 'GET':
                if ($this->request->query->get('denied') != null) {
                    throw new BlimpException(Response::HTTP_UNAUTHORIZED, 'Access denied');
                } else {
                    $key = $this->getKey();

                    if ($key == '') {
                        /* Get oauth_data */
                        return $this->requestOAuthToken();
                    } else {
                        $oauth_token = $this->getOauthToken();
                        $oauth_verifier = $this->getOauthVerifier();

                        $oauth_token_secret = $this->recoverSessionData($key);

                        $pipe = strpos($oauth_token_secret, '|');
                        if ($pipe !== false) {
                            $other = substr($oauth_token_secret, $pipe + 1);
                            $oauth_token_secret = substr($oauth_token_secret, 0, $pipe);

                            $data = [];
                            parse_str($other, $data);
                        }

                        if (strlen($oauth_token_secret) == 0 || $oauth_token == '' || $oauth_verifier == '') {
                            throw new BlimpException(Response::HTTP_INTERNAL_SERVER_ERROR, 'INVALID_OAUTH1_SESSION_DATA');
                        } else {
                            /* Get oauth_data */
                            $access_token = $this->requestAccessToken($oauth_token, $oauth_verifier, $oauth_token_secret);

                            if ($access_token instanceof Response) {
                                return $access_token;
                            } else {
                                return $this->processAccountData($access_token);
                            }
                        }
                    }
                }

                break;

            default:
                throw new BlimpException(Response::HTTP_METHOD_NOT_ALLOWED, 'Method not allowed');
        }
    }
}
