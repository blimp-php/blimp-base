<?php
namespace Blimp\BatchRequest\Rest;

use Blimp\Base\BlimpException;
use Pimple\Container;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\HttpKernelInterface;

class BatchRequest {
    public function process(Container $api, Request $request) {
        try {
            switch ($request->getMethod()) {
                case 'POST':
                    $include_headers = $request->query->get('include_headers');

                    $collected = [];

                    $data = $request->attributes->get('data');

                    if($data != null) {
                        $requests = $data['requests'];

                        if($requests != null) {
                            $requests = json_decode($requests, true);
                            foreach ($requests as $sub) {
                                $url = $sub['relative_url'];

                                $get = [];
                                $post = [];
                                $attributes = [];
                                $cookies = [];
                                $files = [];
                                $server = [];
                                $body = null;

                                $url_parts = \parse_url($url);
                                if (array_key_exists('query', $url_parts)) {
                                    $query = $url_parts['query'];
                                    \parse_str($query, $get);
                                    $url = $url_parts['path'];
                                }

                                $sub_body = $sub['body'];

                                if(\is_string($sub_body)) {
                                    if (substr($sub_body, 0, 1) == '{') {
                                        $body = $sub_body;
                                    } else {
                                        \parse_str($sub_body, $post);
                                    }
                                } else {
                                    $body = \json_encode($sub_body);
                                }

                                if(!array_key_exists('access_token', $get) && !array_key_exists('access_token', $post)) {
                                    $get['access_token'] = "GET IT";
                                }

                                $sub_files = $sub['attached_files'];
                                if($sub_files != null) {
                                    if(!is_array($sub_files)) {
                                        $sub_files = [$sub_files];
                                    }

                                    foreach ($sub_files as $file) {
                                        $up = $request->files->get($file);
                                        if($up != null) {
                                            $files[] = $up;
                                        }
                                    }
                                }

                                $server['REQUEST_URI'] = $url;

                                $request = new Request(
                                    $get,
                                    $post,
                                    $attributes,
                                    $cookies,
                                    $files,
                                    $server,
                                    $body
                                );

                                $response = $api['http.kernel']->handle($request, HttpKernelInterface::SUB_REQUEST);

                                $data = [];
                                $content_type = $request->response->get('Content-Type');
                                if (!empty($content_type) && strpos($content_type, 'application/json') === 0) {
                                    $data['body'] = json_decode($request->getContent(), true);
                                } else {
                                    $data['body'] = $request->getContent();
                                }

                                if($include_headers == null || $include_headers != 'false')

                                $collected[] = $response->getContent();
                            }
                        }
                    }

                    $response = new JsonResponse($collected, Response::HTTP_OK);

                    return $response;

                    break;

                default:
                    throw new BlimpException(Response::HTTP_METHOD_NOT_ALLOWED, "Method not allowed");
            }
        } catch (Exception $e) {
            $error = 'server_error';
            $error_description = 'Unknown error. ' . $e->getMessage();
        }
    }
}
