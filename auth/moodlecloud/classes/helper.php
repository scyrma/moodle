<?php

namespace auth_moodlecloud;
defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/local/aws/sdk/aws-autoloader.php');;

class helper {

    /**
     * The version of the API to use on the SSO server.
     * @const APIVERSION
     */
    const APIVERSION = '1';

    public static function get_client() {
        static $client = null;

        if (null === $client) {
            $client = new \GuzzleHttp\Client();
        }

        return $client;
    }

    public static function isConfigured() {
        $ssoserver  = get_config('auth_moodlecloud', 'ssoserver');
        if (false === $ssoserver) {
            return false;
        }

        return true;
    }

    public static function call($service, $parameters) {

        $classname = '\\auth_moodlecloud\\services\\' . $service;
        if (!class_exists($classname)) {
            throw new \coding_exception('Service not found');
        }

        if (!self::isConfigured()) {
            return null;
        }

        $postData = [];
        // Set the wwwroot.
        $postData['subdomain'] = get_config('auth_moodlecloud', 'ssoapisite');
        // Authenticate to the API.
        $postData['apikey'] = get_config('auth_moodlecloud', 'ssoapikey');

        // And the rest of the parameters.
        foreach ($parameters as $key => $value) {
            $postData[$key] = $value;
        }

        // Verify the parameters.
        $classname::verify_parameters($parameters);

        $ssoserver  = get_config('auth_moodlecloud', 'ssoserver');
        $request   = $ssoserver . '/api/v' . self::APIVERSION . '/' . $classname::target();

        try {
            $response = self::get_client()->request(
                'POST',
                $request,
                [
                    'exceptions'  => false,
                    'verify'      => false,
                    'form_params' => $postData,
                    'headers' => [
                        // Magic header so that varnish on signup lets us through.
                        'X-MC-API' => 'Eivik6Qu',
                    ],
                ]
            );
        }
        catch (\GuzzleHttp\Exception\RequestException $e) {
            $response = null;
        }
        catch (\GuzzleHttp\Exception\TransferException $e) {
            $response = null;
        }
        catch (\Exception $e) {
            $response = null;
        }
        finally {
            return $classname::normalise_response($response);
        }
    }
}
