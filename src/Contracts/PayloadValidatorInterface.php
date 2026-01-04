<?php

namespace SprayMedia\Contracts;

/**
 * Interface PayloadValidatorInterface
 *
 * Defines the contract for validating an incoming request for a protected media file.
 * Its responsibility is to check the signature, expiration, and any other
 * business rules, and to return the verified payload if valid.
 *
 * @package SprayMedia\Contracts
 */
interface PayloadValidatorInterface
{
    /**
     * Validates the incoming request and returns its payload upon success.
     *
     * @param array $request Raw request data (e.g., query params with 'data' and 'signature').
     * @return array The validated and decoded payload data (e.g., ['id' => int, 'action' => 'view'|'download', 'expires_at' => int|null, 'metadata' => array]).
     * @throws \Exception If the validation fails (e.g., missing signature, bad signature, expired).
     */
    public function validate(array $request): array;
}
