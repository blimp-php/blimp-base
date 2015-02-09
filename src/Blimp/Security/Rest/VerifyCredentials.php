<?php
namespace Blimp\Security\Rest;

use Blimp\Base\BlimpException;
use Blimp\Security\Authentication\BlimpToken;
use Pimple\Container;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class VerifyCredentials {
    public function process(Container $api, Request $request) {
        $data = $request->attributes->get('data');

        // inputs
        $input_token = $data['input_token'];

        $include_entities = $data['include_entities'];

        $client_id = $data['client_id'];
        $client_secret = $data['client_secret'];
        $redirect_uri = $data['redirect_uri'];

        $token = $api['security']->getToken();

        switch ($request->getMethod()) {
            case 'GET':
                if (empty($input_token)) {
                    throw new BlimpException(Response::HTTP_BAD_REQUEST, 'invalid_token', 'The access token to inspect is invalid.');
                }

                $query_builder = $api['dataaccess.mongoodm.documentmanager']()->createQueryBuilder();
                $query_builder->eagerCursor(true);
                $query_builder->find('Blimp\Security\Documents\AccessToken');

                $query_builder->field('_id')->equals($input_token);

                $query = $query_builder->getQuery();

                $access_token = $query->getSingleResult();

                if ($access_token != null) {
                    if ($access_token->getExpires() != null && $access_token->getExpires()->getTimestamp() - time() < 0) {
                        throw new BlimpException(Response::HTTP_UNAUTHORIZED, 'invalid_token', 'The access token is expired.');
                    }

                    $real_client_id;
                    $real_client_secret;

                    $authorization_header = null;
                    if ($request->headers->has('authorization')) {
                        $authorization_header = $auth = $request->headers->get('authorization');
                    } else if (isset($_SERVER['HTTP_AUTHORIZATION'])) {
                        $authorization_header = $_SERVER['HTTP_AUTHORIZATION'];
                    } else if (isset($_SERVER['PHP_AUTH_DIGEST'])) {
                        $authorization_header = $_SERVER['PHP_AUTH_DIGEST'];
                    }

                    if ($token != null && $token instanceof BlimpToken && $token->isAuthenticated() && $token->getUser() == null) {
                        if ($client_id !== null || $client_secret !== null) {
                            throw new BlimpException(Response::HTTP_UNAUTHORIZED, 'invalid_client', 'The request utilizes more than one mechanism for authenticating the client.');
                        }

                        $real_client_id = $token->getAccessToken()->getClientID();
                        $real_client_secret = $token->getAccessToken()->getClient()->getSecret();
                    } else if ($authorization_header !== null) {
                        if ($client_id !== null || $client_secret !== null) {
                            throw new BlimpException(Response::HTTP_UNAUTHORIZED, 'invalid_client', 'The request utilizes more than one mechanism for authenticating the client.');
                        }

                        if (strpos($authorization_header, 'Basic') === 0) {
                            $real_client_id = $request->headers->get('PHP_AUTH_USER');
                            $real_client_secret = $request->headers->get('PHP_AUTH_PW');

                            if ($real_client_id === null || $real_client_secret === null) {
                                throw new BlimpException(Response::HTTP_UNAUTHORIZED, 'invalid_client', 'Invalid client authentication.');
                            }
                        } else {
                            throw new BlimpException(Response::HTTP_UNAUTHORIZED, 'invalid_client', 'Unsupported client authentication.');
                        }
                    } else {
                        if (empty($client_id)) {
                            throw new BlimpException(Response::HTTP_UNAUTHORIZED, 'invalid_client', 'No client authentication included.');
                        }

                        $real_client_id = $client_id;
                        $real_client_secret = $client_secret !== null ? $client_secret : '';
                    }

                    if($access_token->getClientID() !== $real_client_id) {
                        throw new BlimpException(Response::HTTP_UNAUTHORIZED, 'invalid_client', 'Invalid client_id.');
                    }

                    $client = $access_token->getClient();

                    $must_be_public = false;
                    if (empty($real_client_secret)) {
                        $must_be_public = true;
                    } else {
                        if ($client->getSecret() !== $real_client_secret) {
                            throw new BlimpException(Response::HTTP_UNAUTHORIZED, 'invalid_client', 'Client authentication failed.');
                        }

                        $must_be_public = false;
                    }

                    $uris = $client->getRedirectURI();
                    $found = false;
                    if ($redirect_uri !== null) {
                        foreach ($uris as $uri) {
                            $client_redirecturl = $uri->getUri();
                            if (strpos($redirect_uri, $client_redirecturl) === 0) {
                                $parcial = $uri->getParcial();
                                if ($parcial || $redirect_uri === $client_redirecturl) {
                                    if(!$must_be_public || $uri->getPublic()) {
                                        $found = true;
                                        break;
                                    }
                                }
                            }
                        }
                    }

                    if ($redirect_uri !== null && !$found) {
                        throw new BlimpException(Response::HTTP_UNAUTHORIZED, 'invalid_request', 'Unauthorized redirect_uri.');
                    } else if ($must_be_public && !$found) {
                        throw new BlimpException(Response::HTTP_UNAUTHORIZED, 'invalid_client', 'Client authentication failed.');
                    }

                    $data = [];

                    $scope = $access_token->getScope();
                    if(!$scope) {
                        $data['scope'] = $scope;
                    }

                    $expires = $access_token->getExpires();
                    if(!$expires) {
                        $data['expires_at'] = $expires;
                    }

                    $data['issued_at'] = $access_token->getCreated();

                    $data['client_id'] = $access_token->getClientId();

                    $profile_id = $access_token->getProfileId();
                    if(!$profile_id) {
                        $data['profile_id'] = $profile_id;
                    }

                    if (boolval($include_entities) && $include_entities != 'false') {
                        $data['client'] = $api['dataaccess.mongoodm.utils']->toStdClass($client);
                        unset($data['client']->secret);
                        unset($data['client']->redirectUri);

                        $profile = $access_token->getProfile();
                        if(!empty($profile)) {
                            $data['profile'] = $api['dataaccess.mongoodm.utils']->toStdClass($profile);
                        }
                    }

                    $response = new JsonResponse();
                    $response->setStatusCode(Response::HTTP_OK);
                    $response->headers->set('Cache-Control', 'no-store');
                    $response->headers->set('Pragma', 'no-cache');
                    $response->setPrivate();
                    $response->setData($data);

                    return $response;
                }

                throw new BlimpException(Response::HTTP_UNAUTHORIZED, 'invalid_token', 'The access token is invalid');

                break;

            default:
                throw new BlimpException(Response::HTTP_METHOD_NOT_ALLOWED, 'Method not allowed');
        }
    }
}
