<?php

namespace App\Http\Services;

use App\Ident;
use App\User;

class IdentService
{
    public function __construct()
    {

    }

    public function saveIdent($identifier, $userId)
    {
        $ident = Ident::where("identifier", $identifier)->first();
        if ($ident == null)
        {
            $ident = new Ident();
            $ident->identifier = $identifier;
            $ident->user_id = $userId;
            $ident->save();
        }
        return $ident;
    }

    public function userByIdent($identifier)
    {
        $ident = Ident::where("identifier", $identifier)->first();
        if ($ident == null)
        {
            return null;
        }
        return User::where("id", $ident->user_id)->first();
    }
}