<?php

namespace App\Commands;

use App\Commands\Command;

use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Bus\SelfHandling;
use Illuminate\Contracts\Queue\ShouldBeQueued;
use DB;
use Carbon\Carbon;
use App\QmMatch;
use App\QmMatchPlayer;
use App\QmQueueEntry;

class FindOpponent extends Command implements SelfHandling, ShouldBeQueued
{

    use InteractsWithQueue, SerializesModels;

    public $qEntryId = null;

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct($id)
    {
        //
        $this->qEntryId = $id;
    }

    public function queue($queue, $arguments)
    {
        $queue->pushOn('findmatch', $arguments);
    }

    /**
     * Execute the command.
     *
     * @return void
     */
    public function handle()
    {
        $this->delete();
        $qEntry = QmQueueEntry::find($this->qEntryId);

        if ($qEntry === null) {
            //error_log("qEntry is null\n");
            return;
        }

        $qEntry->touch();

        $qmPlayer = $qEntry->qmPlayer;

        // A player could cancel out of queue before this function runs
        if ($qmPlayer === null) {
            //error_log("qmPlayer is null");
            $qEntry->delete();
            return;
        }

        // Skip if the player has already been matched up
        if ($qmPlayer->qm_match_id !== null) {
            //error_log("qmPlayer->qm_match_id is not null");
            $qEntry->delete();
            return;
        }

        $history = $qEntry->ladderHistory;

        if ($history === null) {
            //error_log("history is null");
            $qEntry->delete();
            return;
        }

        $ladder = $history->ladder;

        if ($ladder === null) {
            //error_log("ladder is null");
            $qEntry->delete();
            return;
        }

        $player = $qmPlayer->player;

        if ($player === null) {
            //error_log("player is null");
            $qEntry->delete();
            return;
        }
        $rating = $player->rating->rating;
        // map_bitfield is an old and unused bit of code
        $qmPlayer->map_bitfield = 0xffffffff;

        // This will throw an exception when a not null field is null
        $qmPlayer->save();

        $ladder = $qmPlayer->ladder;
        $ladder_rules = $ladder->qmLadderRules;

        /* Try to find a matchup
         * Matchups are based on the player's rating,
         * The absolute value of the difference of me and every other player is calculated.
         * Any players whose difference is greater 100 is thrown out with some exceptions
         * If a player has been waiting a long time for a matchup he should get some special
         * treatment.  To allow for this, the player rating difference gets wait time, in
         * seconds, subtracted from it.
         * If 2 players rated 1200, and 1400 are the only players a match won't be made
         * until one player has been waiting for 100 seconds 1400-1200-100seconds = 100
         *
         * The ratio of seconds is tunable per ladder
         */

        $query = QmQueueEntry::where('qm_match_player_id', '<>', $qEntry->qmPlayer->id)
            ->where('ladder_history_id', '=', $history->id)
            ->select(DB::raw("*,"
                . "TIMESTAMPDIFF(SECOND, created_at, updated_at) * {$ladder_rules->rating_per_second} as rating_time,"
                . "TIMESTAMPDIFF(SECOND, created_at, updated_at) * {$ladder_rules->points_per_second} as points_time"))
            ->havingRAW("rating_time + {$ladder_rules->max_difference} > ABS(rating - {$rating})"
                . "AND points_time + {$ladder_rules->max_points_difference} > ABS(points - {$qEntry->points})");

        //error_log($query->toSql());
        $qmOpns = $query->get();

        //var_dump($qmOpns);
        //error_log("Queried QmQueueEntry\n");
        $qmOpns = $qmOpns->shuffle();

        if ($qmOpns->count() >= $ladder_rules->player_count - 1) {
            //error_log("checking qmOpns\n");
            // Randomly choose the opponents from the best matches. To prevent
            // long runs of identical matchups.
            $qmOpns = $qmOpns->shuffle()->take($ladder_rules->player_count - 1);
            // Randomly select a map
            $common_maps = array();

            $qmMaps = $ladder->mapPool->maps;
            foreach ($qmMaps as $qmMap) {
                $match = true;
                if (
                    array_key_exists($qmMap->bit_idx, $qmPlayer->map_side_array())
                    &&
                    $qmPlayer->map_side_array()[$qmMap->bit_idx] > -2
                    &&
                    in_array($qmPlayer->map_side_array()[$qmMap->bit_idx], $qmMap->sides_array())
                ) {
                    foreach ($qmOpns as $qOpn) {
                        //error_log("qOpn->rating_time = {$qOpn->rating_time}");
                        $opn = $qOpn->qmPlayer;
                        if ($opn === null) {
                            $qOpn->delete();
                            $qEntry->delete();
                            return;
                        }
                        if (
                            array_key_exists($qmMap->bit_idx, $opn->map_side_array())
                            &&
                            ($opn->map_side_array()[$qmMap->bit_idx] < -1
                                ||
                                !in_array($opn->map_side_array()[$qmMap->bit_idx], $qmMap->sides_array()))
                        ) {
                            $match = false;
                        }
                    }
                } else
                    $match = false;

                if ($match)
                    $common_maps[] = $qmMap;
            }
            if (count($common_maps) < 1) {
                $qmPlayer->touch();
                return;
            }

            $qEntry->delete();

            $recentlyPlayedFlag = true; //flag telling if map has been played in last 3 games
            $randomMapIndex = mt_rand(0, count($common_maps) - 1);
            $count = 0;

            while ($recentlyPlayedFlag && $count < 50) {  //keep looping until a map is found that has not been played in last 3 games and count less than 50
                $recentlyPlayedFlag = false;
                $count++;

                $randomMapIndex = mt_rand(0, count($common_maps) - 1);
                $randomMapName = $common_maps[$randomMapIndex]->map->name;

                $recentGames = array_slice($player->playerGames(), 0, 3);  //grab the last 3 games p1 has played

                //loop through the player's last 3 games and check if any of the 3 played maps === the randomly picked map
                foreach ($recentGames as $recentGame) {
                    $recentMapName = $recentGame->scen;

                    if ($recentMapName === $randomMapName) { //is recently played map equal to randomly picked map
                        $recentlyPlayedFlag = true;
                        break;
                    }
                }

                if ($recentlyPlayedFlag)
                    continue;

                //check opps last 3 games (rly it's just be 1 opponent being checked)
                foreach ($qmOpns as $qOpn) {
                    $oppPlayer = $qOpn->qmPlayer->player;

                    $oppRecentGames = array_slice($oppPlayer->playerGames(), 0, 3);  //grab the last 3 games p2 (opponent) has played

                    foreach ($oppRecentGames as $oppRecentGame) { //loop through the opponent's last 3 games and check if any of the 3 played maps === the randomly picked map
                        $oppRecentMapName = $oppRecentGame->scen;


                        if ($oppRecentMapName === $qmMap->bit_idx) { //is recently played map by opponent equal to randomly picked map
                            $recentlyPlayedFlag = true;
                            break;
                        }
                    }
                }
            }

            // Create the qm_matches db entry
            $qmMatch = new \App\QmMatch();
            $qmMatch->ladder_id = $qmPlayer->ladder_id;
            $qmMatch->qm_map_id = $common_maps[$randomMapIndex]->id;
            $qmMatch->seed = mt_rand(-2147483647, 2147483647);

            // Create the Game
            $game = \App\Game::genQmEntry($qmMatch);
            $qmMatch->game_id = $game->id;
            $qmMatch->save();

            $game->qm_match_id = $qmMatch->id;
            $game->save();

            $qmMap = $qmMatch->map;
            $spawn_order = explode(',', $qmMap->spawn_order);

            // Set up player specific information
            // Color will be used for spawn location
            $qmPlayer->color = 0;
            $qmPlayer->location = $spawn_order[$qmPlayer->color] - 1;
            $qmPlayer->qm_match_id = $qmMatch->id;
            $qmPlayer->tunnel_id = $qmMatch->seed + $qmPlayer->color;

            $psides = explode(',', $qmPlayer->mapSides->value);

            if (count($psides) > $qmMap->bit_idx)
                $qmPlayer->actual_side = $psides[$qmMap->bit_idx];


            if ($qmPlayer->actual_side < -1) {
                $qmPlayer->actual_side = $qmPlayer->chosen_side;
            }

            $qmPlayer->save();

            $perMS = array_values(array_filter($qmMap->sides_array(), function ($s) {
                return $s >= 0;
            }));
            $color = 1;
            foreach ($qmOpns as $qOpn) {
                $opn = $qOpn->qmPlayer;
                $qOpn->delete();

                if ($opn === null) {
                    $qEntry->delete();
                    return;
                }

                $osides = explode(',', $opn->mapSides->value);

                if (count($osides) > $qmMap->bit_idx)
                    $opn->actual_side = $osides[$qmMap->bit_idx];

                if ($opn->actual_side  < -1) {
                    $opn->actual_side = $opn->chosen_side;
                }

                if ($opn->actual_side == -1) {
                    $opn->actual_side = $perMS[mt_rand(0, count($perMS) - 1)];
                }
                $opn->color = $color++;
                $opn->location = $spawn_order[$opn->color] - 1;
                $opn->qm_match_id = $qmMatch->id;
                $opn->tunnel_id = $qmMatch->seed + $opn->color;
                $opn->save();
            }

            if ($qmPlayer->actual_side == -1) {
                $qmPlayer->actual_side = $perMS[mt_rand(0, count($perMS) - 1)];
            }
            $qmPlayer->save();
        }
    }
}
