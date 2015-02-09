<?php
namespace Blimp\Accounts\Oauth2;

use Blimp\Base\BlimpException;
use Pimple\Container;
use Blimp\Accounts\Oauth2\Protocol;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

abstract class Oauth2AccessToken {
    protected $api;
    protected $request;

    /* endpoints */

    abstract public function getAuthorizationEndpoint();
    abstract public function getAccessTokenEndpoint();

    /* application credentials */

    abstract public function getClientID();
    abstract public function getClientSecret();

    /* access parameters */

    /*
     * The URL to redirect to after the user clicks a button in the third party dialog.
     *
     * This implementation uses request parameter "redirect_uri".
     * Can be overrided.
     */
    public function getRedirectURI() {
        return $this->request->query->get("redirect_uri") != NULL ? $this->request->query->get("redirect_uri") : ($this->request->query->get("state") != NULL ? $this->request->query->get("state") : "");
    }

    /*
     * A list of permission which the user will be asked to grant to the application.
     *
     * Must be overrided.
     */
    abstract public function getScope();

    /*
     * Indicates if the user should be re-prompted for login and consent.
     *
     * This implementation uses request parameter "force_login" and defaults do "false".
     * Can be overrided.
     */
    public function getForceLogin() {
        return $this->request->query->get("force_login") != NULL && ($this->request->query->get("force_login") == "true" || $this->request->query->get("force_login") == "1");
    }

    /*
     * Creates other request parameters specific of the third party.
     *
     * Should be overrided if needed.
     */
    public function getOtherAuthorizationRequestParams() {
    }

    public function fillOtherAccessTokenRequestParams(array $params) {
    }

    /* return data */

    /*
     * The authorization code returned from the initial request.
     */
    public function getCode() {
        return $this->request->query->get("code") != NULL ? $this->request->query->get("code") : "";
    }

    /* interaction */

    public function requestCode() {
        $scope = $this->getScope();

        $oss = $this->getAuthorizationEndpoint($this->api);
        $oss .= "?response_type=code";
        $oss .= "&client_id=" . $this->getClientID();
        if (strlen($scope) != 0) {
            $oss .= "&scope=" . scope;
        }
        $oss .= "&redirect_uri=" . urlencode($this->request->getUriForPath($this->request->getPathInfo()));
        $oss .= "&state=" . urlencode($this->getRedirectURI());

        $oss .= $this->getOtherAuthorizationRequestParams();

        $response = new Response(Response::HTTP_TEMPORARY_REDIRECT);
        $response->headers->set('Location', $oss);

        return $response;
    }

    public function requestAccessToken($code) {
        $params = [
            "grant_type" => "authorization_code",
            "client_id" => $this->getClientID(),
            "client_secret" => $this->getClientSecret(),
            "redirect_uri" => $this->request->getUriForPath($this->request->getPathInfo()),
            "code" => $code
        ];

        $this->fillOtherAccessTokenRequestParams($params);

        $access_token_data = Protocol::post($this->getAccessTokenEndpoint(), $params);

        return $access_token_data;
    }

    /* do stuff */

    abstract public function processAccountData(array $access_token);

    public function process(Request $request, Container $api) {
        $this->api = $api;
        $this->request = $request;

        switch ($this->request->getMethod()) {
            case 'GET':
                if ($this->request->query->get("error") != NULL && $this->request->query->get("error") == "access_denied") {
                    throw new BlimpException(Response::UNAUTHORIZED, "Access denied");
                } else {
                    $code = $this->getCode($this->request);

                    if (strlen($code) == 0) {
                        return $this->requestCode();
                    } else {
                        /* Get access_token */
                        $access_token = $this->requestAccessToken($code);

                        if ($access_token instanceof Response) {
                            return $access_token;
                        } else {
                            return $this->processAccountData($access_token);
                        }
                    }
                }

                break;

            default:
                throw new BlimpException(Response::HTTP_METHOD_NOT_ALLOWED, "Method not allowed");
        }
    }
}
