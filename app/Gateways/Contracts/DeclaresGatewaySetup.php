<?php

namespace App\Gateways\Contracts;

/**
 * Declares credentials / params / endpoints a merchant (or admin) must configure.
 * Endpoints are typically managed in Durpalla Admin; merchants still see them as instructions.
 */
interface DeclaresGatewaySetup
{
    /**
     * @return array{
     *   summary?: string,
     *   credentials: list<array{key:string,label:string,required?:bool,secret?:bool,help?:string}>,
     *   params: list<array{key:string,label:string,required?:bool,help?:string}>,
     *   endpoints: list<array{key:string,label:string,required?:bool,help?:string,admin_only?:bool}>
     * }
     */
    public static function setupSchema(): array;
}
