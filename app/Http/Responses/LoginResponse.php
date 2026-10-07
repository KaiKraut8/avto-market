<?php

namespace App\Http\Responses;

use Laravel\Fortify\Http\Responses\LoginResponse as FortifyLoginResponse;

class LoginResponse extends FortifyLoginResponse
{
    use ReturnTo;

    public function toResponse($request)
    {
        $to = $request->wantsJson() ? null : $this->returnTo($request);

        return $to ? redirect($to) : parent::toResponse($request);
    }
}
