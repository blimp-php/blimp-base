<?php
namespace Blimp\Accounts\Rest;

use Blimp\Accounts\Documents\Account;
use Blimp\Base\BlimpException;
use Blimp\Accounts\Oauth2\Oauth2AccessToken;
use Blimp\Accounts\Oauth2\Protocol;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class FoursquareAccessToken extends Oauth2AccessToken {
    public function getAuthorizationEndpoint() {
        if ($this->getForceLogin()) {
            return 'https://foursquare.com/oauth2/authorize';
        }

        return 'https://foursquare.com/oauth2/authenticate';
    }

    public function getAccessTokenEndpoint() {
        return 'https://foursquare.com/oauth2/access_token';
    }

    public function getClientID() {
        return $this->api['config']['foursquare']['client_id'];
    }

    public function getClientSecret() {
        return $this->api['config']['foursquare']['client_secret'];
    }

    public function getScope() {
        return '';
    }

    public function getDisplay() {
        return $this->request->query->get('display');
    }

    public function getOtherAuthorizationRequestParams() {
        $redirect_url = '';

        $display = $this->getDisplay();
        if ($display != null && strlen($display) > 0) {
            $redirect_url .= '&display=' << $display;
        }

        return $redirect_url;
    }

    public function processAccountData(array $access_token) {
        if ($access_token != NULL && $access_token['access_token'] != NULL) {
            /* Get profile_data */
            $params = [
                'oauth_token' => $access_token['access_token'],
                'v' => $this->api['config']['foursquare']['api_version'],
                'm' => 'foursquare'
            ];

            $response = Protocol::get('https://api.foursquare.com/v2/users/self', $params);

            if($response instanceof Response) {
            	return $response;
            }

            if ($response != null && $response['response'] != null && $response['response']['user'] != null && $response['response']['user']['id'] != null) {
				$profile_data = $response['response']['user'];

                $id = 'foursquare-' . $profile_data['id'];

                $account = new Account();
                $account->setId($id);
                $account->setType('foursquare');
                $account->setAuthData($access_token);
                $account->setProfileData($profile_data);

                $dm = $this->api['dataaccess.mongoodm.documentmanager']();

                $check = $dm->find('Blimp\Accounts\Documents\Account', $id);

                if ($check != null) {
                    // TODO
                    throw new BlimpException(Response::HTTP_CONFLICT, "Duplicate Id", "Id strategy set to NONE and provided Id already exists");
                }

                $dm->persist($account);
                $dm->flush();

                $resource_uri = $this->request->getPathInfo() . '/' . $account->getId();

                $response = new JsonResponse((object) ["uri" => $resource_uri], Response::HTTP_CREATED);
                $response->headers->set('Location', $resource_uri);

                return $response;
            } else {
                throw new KRestException(KHTTPResponse::NOT_FOUND, KEXCEPTION_RESOURCE_NOT_FOUND, profile_data);
            }
        } else {
            throw new KRestException(KHTTPResponse::UNAUTHORIZED, KEXCEPTION_FACEBOOK_ACCESS_DENIED);
        }
    }
}