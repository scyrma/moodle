<?php

namespace auth_moodlecloud;
defined('MOODLE_INTERNAL') || die();

require_once(dirname(dirname(dirname(__DIR__))) . '/local/filestorage/sdk/aws-autoloader.php');

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

    public static function createRequest($endpoint, $method = null, $options = null) {
        if (!self::isConfigured()) {
            return null;
        }

        if (null === $method) {
            $method = 'POST';
        }

        if (null === $options) {
            $options = array(
                    'exceptions'    => false,
                    'verify'        => false,
                );
        }


        $ssoserver  = get_config('auth_moodlecloud', 'ssoserver');
        $endpoint   = $ssoserver . '/api/v' . self::APIVERSION . '/' . $endpoint;

        return (new \GuzzleHttp\Psr7\Request($method, $endpoint, $options));
    }

    public static function send(\GuzzleHttp\Psr7\Request $request) {
        return self::get_client()->send($request);
    }

    public static function call($service, $parameters) {
        global $CFG;

        $classname = '\\auth_moodlecloud\\services\\' . $service;
        if (!class_exists($classname)) {
            throw new \coding_exception('Service not found');
        }

        if (!self::isConfigured()) {
            return null;
        }

        // Verify the parameters.
        $classname::verify_parameters($parameters);

        // Attempt to create the request.
        $request = self::createRequest($classname::target());
        if (null === $request) {
            return;
        }

        $postBody = $request->getBody();

        // Set the wwwroot.
        $postBody->setField('subdomain', get_config('auth_moodlecloud', 'ssoapisite'));

        // Authenticate to the API.
        $postBody->setField('apikey', get_config('auth_moodlecloud', 'ssoapikey'));

        // And the rest of the parameters.
        foreach ($parameters as $key => $value) {
            $postBody->setField($key, $value);
        }

        try {
            $response = self::send($request);
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
