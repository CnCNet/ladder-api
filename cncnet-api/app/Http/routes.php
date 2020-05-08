<?php

use \App\User;

Route::get('/', function ()
{
    return redirect('ladder/');
});

//Route::get('/patch', 'LadderController@addLadder');

Route::get('/ladder-champions/{game}', 'LeagueChampionsController@getLeagueChampions');

Route::group(['prefix' => 'ladder/', 'middleware' => ['auth', 'cache.public'], 'guestsAllowed' => true], function()
{
    Route::get('/', 'LadderController@getLadders');
    Route::get('{date}/{game}', 'LadderController@getLadderIndex');
    Route::get('{date}/{game}/games', 'LadderController@getLadderGames');
    Route::get('{date}/{tier}/{game}', 'LadderController@getLadderIndex');
    Route::get('{date}/{game}/player/', 'LadderController@getLadderIndex');
    Route::get('{date}/{game}/player/{player}', 'LadderController@getLadderPlayer');
    Route::get('{date}/{game}/games/{gameId}', 'LadderController@getLadderGame');
    Route::get('{date}/{game}/games/{gameId}/{reportId}', 'LadderController@getLadderGame');
    Route::get('{date}/{game}/badges', 'LadderController@getBadgesIndex');
});

Route::controllers
([
	'auth' => 'Auth\AuthController',
	'password' => 'Auth\PasswordController',
]);

Route::get('/admin', ['middleware' => 'auth', 'canEditAnyLadders' => true, 'uses' => 'AdminController@getAdminIndex']);


Route::group(['prefix' => 'admin/', 'middleware' => 'auth', 'canAdminLadder' => true], function ()
{
    Route::get('users/', 'AdminController@getManageUsersIndex');
});

Route::group(['prefix' => 'admin/setup/{ladderId}', 'middleware' => 'auth', 'canModLadder' => true], function ()
{
    Route::get('edit', 'AdminController@getLadderSetupIndex');
    Route::post('ladder', 'LadderController@saveLadder');

    Route::post('addSide', ['middleware' => 'auth', 'canAdminLadder' => true, 'uses' => 'AdminController@addSide']);
    Route::post('remSide', ['middleware' => 'auth', 'canAdminLadder' => true, 'uses' => 'AdminController@remSide']);

    Route::post('rules', ['middleware' => 'auth', 'canAdminLadder' => true, 'uses' => 'AdminController@postLadderSetupRules']);
    Route::post('qmmap', ['middleware' => 'auth', 'canAdminLadder' => true, 'uses' => 'AdminController@postQuickMatchMap']);
    Route::post('remqmap', ['middleware' => 'auth', 'canAdminLadder' => true, 'uses' => 'AdminController@removeQuickMatchMap']);
    Route::get('downmap/{mapId}', ['middleware' => 'auth', 'canAdminLadder' => true, 'uses' => 'AdminController@moveDownQuickMatchMap']);
    Route::get('upmap/{mapId}', ['middleware' => 'auth', 'canAdminLadder' => true, 'uses' => 'AdminController@moveUpQuickMatchMap']);
    Route::post('editmap', ['middleware' => 'auth', 'canAdminLadder' => true, 'uses' => 'AdminController@editMap']);

    Route::post('add/admin', ['middleware' => 'auth', 'group' => User::God, 'uses' => 'AdminController@addAdmin']);
    Route::post('remove/admin', ['middleware' => 'auth', 'group' => User::God, 'uses' => 'AdminController@removeAdmin']);

    Route::post('add/moderator', ['middleware' => 'auth', 'canAdminLadder' => true, 'uses' => 'AdminController@addModerator']);
    Route::post('remove/moderator', ['middleware' => 'auth', 'canAdminLadder' => true, 'uses' => 'AdminController@removeModerator']);

    Route::post('add/tester', 'AdminController@addTester');
    Route::post('remove/tester', 'AdminController@removeTester');
});

Route::group(['prefix' => 'admin/moderate/{ladderId}', 'middleware' => 'auth', 'canModLadder' => true], function ()
{
    Route::get('/games/{cncnetGame}', 'AdminController@getManageGameIndex');
    Route::post('/games/{cncnetGame}/delete', 'AdminController@deleteGame');
    Route::post('/games/switch', 'AdminController@switchGameReport');
    Route::post('/games/wash', 'AdminController@washGame');

    Route::get('/player/{playerId}', 'AdminController@getLadderPlayer');
    Route::get('/player/{playerId}/newban/{banType}', 'AdminController@getUserBan');
    Route::get('/player/{playerId}/editban/{banId}', 'AdminController@editUserBan');
    Route::post('/player/{playerId}/editban/{banId}', 'AdminController@saveUserBan');
    Route::post('/player/{playerId}/editban', 'AdminController@saveUserBan');
});

Route::group(['prefix' => 'account', 'middleware' => 'auth'], function ()
{
    Route::get('/', 'AccountController@getAccountIndex');
    Route::post('/username-status', 'AccountController@toggleUsernameStatus');
    Route::post('/username', 'AccountController@createUsername');
    Route::post('/card', 'AccountController@updatePlayerCard');
    Route::get('/verify', 'AccountController@getNewVerification');
    Route::post('/verify', 'AccountController@createNewVerification');
    Route::get('/verify/{verify_token}', 'AccountController@verifyEmail');
});

Route::group(['prefix' => 'api/v1/auth/', 'middleware' => 'auth.basic.once'], function()
{
    Route::get('/token', 'ApiAuthController@getAuth');
});

Route::group(['prefix' => 'api/v1/auth/'], function()
{
    Route::get('/refresh', 'ApiAuthController@refresh');
    Route::post('/login', 'ApiAuthController@login');
});

Route::group(['prefix' => 'api/v1/'], function ()
{
    Route::get('/user/account', 'ApiUserController@getAccount');
    Route::post('/user/create', 'ApiUserController@createUser');

    // Result Endpoints
    Route::post('/result/{game}/{username}', 'ApiLadderController@postLadder');
    Route::post('/result/{game}/{username}/{pingSent}/{pingReceived}', 'ApiLadderController@postLadder');
    Route::post('/result/ladder/{ladderId}/game/{gameId}/player/{playerId}/pings/{pingsSent}/{pingsReceived}', 'ApiLadderController@newPostLadder');

    // General Endpoints
    Route::get('/ping', 'ApiLadderController@pingLadder');

    // Player Endpoints
    Route::post('/player/{username}', 'ApiPlayerController@createPlayer');

    // Debug
    Route::get('/ladder/raw/{gameId}', 'ApiLadderController@viewRawGame');
    Route::get('/ladder/elo/{gameId}', 'ApiLadderController@awardPoints');

    // QuickMatch Endpoints
    Route::get('/qm/version/{platform}', 'ApiQuickMatchController@clientVersion');
    Route::get('/qm/ladder/{ladderAbbrev}/stats', 'ApiQuickMatchController@statsRequest');
    Route::get('/qm/ladder/{ladderAbbrev}/maps', 'ApiQuickMatchController@mapListRequest');
    Route::post('/qm/{ladderAbbrev}/{playerName}', 'ApiQuickMatchController@matchRequest');
});

// Client endpoints
Route::group(['prefix' => 'api/v1/client'], function()
{
    // Get user nicknames
    Route::post('/accounts/user/nicknames', 'ApiClientController@getUserNicknames');

    // Verify idents
    Route::post('/accounts/verify', 'ApiClientController@verifyAccountsByIdent');
});

// Ladder Endpoints
Route::group(['prefix' => 'api/v1/ladder', 'middleware' => 'cache.long.public'], function()
{
    Route::get('/', 'ApiLadderController@getCurrentLadders');
    Route::get('/{game}/games/recent/{count}', 'ApiLadderController@getLadderRecentGamesList');
    Route::get('/{game}', 'ApiLadderController@getLadder');
    Route::get('/{game}/game/{gameId}', 'ApiLadderController@getLadderGame');
    Route::get('/{game}/winners/', 'ApiLadderController@getLadderWinners');
    Route::get('/{game}/games/recent/{count}', 'ApiLadderController@getLadderRecentGamesList');
});

// Short cache ladder endpoints
Route::group(['prefix' => 'api/v1/ladder', 'middleware' => 'cache.public'], function()
{
    Route::get('/{game}/top/{count}', 'ApiLadderController@getLadderTopList');
    Route::get('/{game}/player/{player}', 'ApiLadderController@getLadderPlayer');
});
