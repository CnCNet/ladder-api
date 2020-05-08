<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Http\Services\AuthService;
use App\Http\Services\IdentService;
use App\Player;
use App\Ladder;

class ApiClientController extends Controller
{
    private $authService;
    private $identService;

    public function __construct()
    {
        $this->authService = new AuthService();
        $this->identService = new IdentService();
    }

    public function getUserNicknames(Request $request)
    {
        $auth = $this->authService->getUser($request);

        if ($auth["user"] === null)
        {
            return response($auth["response"], $auth['status']);
        }

        if ($request->ident == null)
        {
            return response("Bad request", 400);
        }

        $user = $auth["user"];
        $gameAbbreviation = $request->game;
        $ladder = Ladder::where("abbreviation", $gameAbbreviation)->first();

        if ($ladder == null)
        {
            return response("Game doesn't exist", 400);
        }

        // Store ident
        $this->identService->saveIdent($request->ident, $user->id);

        // Unlimited nicks for casual players 
        $playerNicks = \App\Player::where("user_id", $user->id)
            ->where('ladder_id', $ladder->id)
            ->orderBy("id", "desc")
            ->get();

        return $playerNicks;
    }

    public function verifyAccountsByIdent(Request $request)
    {
        $idents = json_decode($request->idents);
        $gameAbbreviation = $request->game;
        $results = [];

        $ladder = Ladder::where("abbreviation", $gameAbbreviation)->first();
        if ($ladder == null)
        {
            return response("Game doesn't exist", 400);
        }

        foreach($idents as $ident)
        {
            $user = $this->identService->userByIdent($ident);
            if ($user == null)
            {
                continue;
            }

            $playerNicks = Player::where("user_id", $user->id)
                ->where('ladder_id', $ladder->id)
                ->orderBy("id", "desc")
                ->select("username")
                ->get();

            $results[] = [
                "ident" => $ident,
                "nicknames" => $playerNicks
            ];
        }

        /* Array
            [
                ident: "XXXXX",
                nicknames: [
                    "nickname" => [
                        "points",
                        "badge",
                        "clan"
                    ]
                ]
            ]
        */
        return $results;
    }

    private function returnPlayerByIdent()
    {
        
    }
}
