<?php

namespace PKP\core;

use stdClass;
use Firebase\JWT\JWT;
use PKP\config\Config;
use UnexpectedValueException;

class PKPJwt extends JWT
{
    /**
     * Decodes a JWT string into a PHP object.
     *
     * @param string                 $jwt            The JWT
     * @param Key|ArrayAccess<string,Key>|array<string,Key> $keyOrKeyArray  The Key or associative array of key IDs
     *                                                                      (kid) to Key objects.
     *                                                                      If the algorithm used is asymmetric, this is
     *                                                                      the public key.
     *                                                                      Each Key object contains an algorithm and
     *                                                                      matching key.
     *                                                                      Supported algorithms are 'ES384','ES256',
     *                                                                      'HS256', 'HS384', 'HS512', 'RS256', 'RS384'
     *                                                                      and 'RS512'.
     * @param stdClass               $headers                               Optional. Populates stdClass with headers.
     *
     * @return stdClass The JWT's payload as a PHP object
     *
     * @throws InvalidArgumentException     Provided key/key-array was empty or malformed
     * @throws DomainException              Provided JWT is malformed
     * @throws UnexpectedValueException     Provided JWT was invalid
     * @throws SignatureInvalidException    Provided JWT was invalid because the signature verification failed
     * @throws BeforeValidException         Provided JWT is trying to be used before it's eligible as defined by 'nbf'
     * @throws BeforeValidException         Provided JWT is trying to be used before it's been created as defined by 'iat'
     * @throws ExpiredException             Provided JWT has since expired, as defined by the 'exp' claim
     *
     * @uses jsonDecode
     * @uses urlsafeB64Decode
     */
    public static function decode(string $jwt, $keyOrKeyArray, stdClass &$headers = null): stdClass
    {
        $tks = explode('.', $jwt);
        
        if (count($tks) !== 3) {
            throw new UnexpectedValueException('Wrong number of segments');
        }

        list($headb64, $bodyb64, $cryptob64) = $tks;

        $payloadRaw = static::urlsafeB64Decode($bodyb64);

        if (null === ($payload = static::jsonDecode($payloadRaw))) {
            throw new UnexpectedValueException('Invalid claims encoding');
        }

        if (is_array($payload)) {
            return parent::decode($jwt, $keyOrKeyArray, $headers);
        }

        if (is_string($payload)) {
            return parent::decode(
                static::encode(
                    [$payload], 
                    Config::getVar('security', 'api_key_secret', ''), 
                    'HS256'
                ), 
                $keyOrKeyArray, 
                $headers
            );
        }
        
        return parent::decode($jwt, $keyOrKeyArray, $headers);
    }
}