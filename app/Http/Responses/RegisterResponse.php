<?php

namespace App\Http\Responses;

use Laravel\Fortify\Http\Responses\RegisterResponse as FortifyRegisterResponse;

class RegisterResponse extends FortifyRegisterResponse
{
    use ReturnTo;

    public function toResponse($request)
    {
        $to = $request->wantsJson() ? null : $this->returnTo($request);

        return $to ? redirect($to) : parent::toResponse($request);
    }
}
