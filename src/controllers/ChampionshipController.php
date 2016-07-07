<?php
use \Psr\Http\Message\ServerRequestInterface as Request;
use \Psr\Http\Message\ResponseInterface as Response;
use Interop\Container\ContainerInterface as ContainerInterface;

/**
 * @copyright   Swiss Tchoukball 2016
 * @author      David Sandoz <david.sandoz@tchoukball.ch>
 */

class Championship {
    protected $ci;

    // Constructor
    public function __construct(ContainerInterface $ci) {
        $this->ci = $ci;
        $this->db = $ci['db'];
    }

    /**
     * Get the list of open categories by season
     * TODO: make it possible to get the closed categories by season with a parameter in the request
     *
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function getCategoriesBySeason(Request $request, Response $response) {
        $lang = getLang($request);

        $query = "SELECT ccps.id,
                     ccps.season,
                     cc.idCategorie AS categoryId,
                     cc.categorie$lang AS categoryName,
                     cc.isNbSpotLimitedByClub,
                     ccps.teamRegistrationFee,
                     ccps.playerLicenseFee,
                     ccps.refereeDefrayalAmount,
                     DATE_FORMAT(ccps.deadline, '%Y-%m-%dT%H:%i:%sZ') AS deadline
              FROM Championnat_Categories_Par_Saison ccps, Championnat_Categories cc
              WHERE TIMESTAMP(ccps.deadline) > NOW()
              AND ccps.categoryId = cc.idCategorie";

        try {
            $result = $this->db->prepare($query);
            $result->execute();
        } catch (PDOException $e) {
            return $response->withStatus(500)
                ->withHeader('Content-Type', 'text/html')
                ->write($e);
        }

        $data = array();
        while ($registration = $result->fetch(PDO::FETCH_ASSOC)) {
            array_push($data, array(
                'id' => intval($registration['id']),
                'season' => array(
                    'startYear' => intval($registration['season']),
                    'name' => getSeasonName($registration['season'])
                ),
                'category' => array(
                    'id' => intval($registration['categoryId']),
                    'name' => $registration['categoryName'],
                    'isNbSpotLimitedByClub' => $registration['isNbSpotLimitedByClub'] == 1
                ),
                'teamRegistrationFee' => intval($registration['teamRegistrationFee']),
                'playerLicenseFee' => intval($registration['playerLicenseFee']),
                'refereeDefrayalAmount' => intval($registration['refereeDefrayalAmount']),
                'deadline' => $registration['deadline']
            ));
        }

        $newResponse = $response->withJson($data);

        return $newResponse;
    }

    private function _registerPlayers(Array $playersId, $teamId, $userId) {

        // Saving the players in the database
        $playerQuery = "INSERT INTO Championnat_Joueurs (
                        teamId,
                        personId,
                        registrationAuthorId,
                        registrationDate
                    )
                    VALUES ";

        foreach ($playersId as $playerId) {
            $playerQuery .= "($teamId, $playerId, $userId, NOW()),";
        }
        $playerQuery = rtrim($playerQuery, ","); // Removing extra comma

        try {
            $this->db->exec($playerQuery);
        } catch (PDOException $e) {
            throw $e;
        }
    }

    /**
     * Register a new team
     * TODO: input validation
     * TODO: send mail to heads of championship and finance
     * 
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function registerTeam(Request $request, Response $response) {
        $registration = $request->getParsedBody();

        // Getting the user ID of the person who filled the form
        try {
            $userId = getUserIdFromUsername($this->db, $_SESSION['__username__']);
        } catch (PDOException $e) {
            return $response->withStatus(500)
                ->withHeader('Content-Type', 'text/html')
                ->write($e);
        }

        // Getting information regarding if a club can only register a limited number of team in a category
        $spotLimitationQuery = "SELECT c.isNbSpotLimitedByClub
                            FROM Championnat_Categories c, Championnat_Categories_Par_Saison cps
                            WHERE c.idCategorie = cps.categoryId
                            AND cps.id = {$registration['categoryBySeasonId']}";
        try {
            $result = $this->db->prepare($spotLimitationQuery);
            $result->execute();
            $data = $result->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            return $response->withStatus(500)
                ->withHeader('Content-Type', 'text/html')
                ->write($e);
        }

        $isNbSpotLimitedByClub = $data['isNbSpotLimitedByClub'] == 1;

        // Getting information about already registered teams and available spots for the club
        if ($isNbSpotLimitedByClub) {
            $availableSpotsQuery = "SELECT
                                (SELECT COUNT(*)
                                 FROM Championnat_Equipes
                                 WHERE idClub = {$registration['clubId']}
                                 AND idCategorieParSaison = {$registration['categoryBySeasonId']}) AS nbRegisteredTeam,
                                (SELECT cpc.nbPlaces
                                 FROM Championnat_Clubs_Places_Categories cpc, Championnat_Categories_Par_Saison cps
                                 WHERE cpc.idCategorie = cps.categoryId
                                 AND cpc.idClub = {$registration['clubId']}
                                 AND cps.id = {$registration['categoryBySeasonId']}) AS nbSpots";
            try {
                $result = $this->db->prepare($availableSpotsQuery);
                $result->execute();
                $data = $result->fetch(PDO::FETCH_ASSOC);
            } catch (PDOException $e) {
                return $response->withStatus(500)
                    ->withHeader('Content-Type', 'text/html')
                    ->write($e);
            }

            $nbAvailableSpots = $data['nbSpots'] - $data['nbRegisteredTeam'];

            if ($nbAvailableSpots <= 0) {
                return $response->withStatus(409)
                    ->withHeader('Content-Type', 'text/plain')
                    ->withAddedHeader('X-Conflict-Error', 'noSpot')
                    ->write('There is no more spot available for your team');
            }
        }

        // Saving the team in the database
        $teamQuery = "INSERT INTO Championnat_Equipes (
                      equipe,
                      idClub,
                      idResponsable,
                      idCategorieParSaison,
                      couleurMaillotDomicile,
                      couleurMaillotExterieur,
                      idLieuDomicile,
                      registrationAuthorId,
                      registrationDate
                  )
                  VALUES (
                      '{$registration['teamName']}',
                      {$registration['clubId']},
                      {$registration['teamManagerId']},
                      {$registration['categoryBySeasonId']},
                      '{$registration['jerseyColorHome']}',
                      '{$registration['jerseyColorAway']}',
                      {$registration['homeVenueId']},
                      {$userId},
                      NOW()
                  )";

        try {
            $this->db->exec($teamQuery);
            $teamId = $this->db->lastInsertId();
            $newResponse = $response;
        } catch (PDOException $e) {
            return $response->withStatus(500)
                ->withHeader('Content-Type', 'text/html')
                ->write($e);
        }
        
        try {
            $this->_registerPlayers($registration['playersId'], $teamId, $userId);
        } catch(PDOException $e) {
            return $response->withStatus(500)
                ->withHeader('Content-Type', 'text/html')
                ->write($e);
        }

        return $response;
    }

    /**
     * Register players in a team
     * TODO: input validation
     * TODO: send mail to heads of championship and finance
     * 
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function registerPlayers(Request $request, Response $response) {
        $registration = $request->getParsedBody();

        // Getting the user ID of the person who filled the form
        try {
            $userId = getUserIdFromUsername($this->db, $_SESSION['__username__']);
        } catch (PDOException $e) {
            return $response->withStatus(500)
                ->withHeader('Content-Type', 'text/html')
                ->write($e);
        }

        try {
            $this->_registerPlayers($registration['playersId'], $registration['teamId'], $userId);
        } catch(PDOException $e) {
            return $response->withStatus(500)
                ->withHeader('Content-Type', 'text/html')
                ->write($e);
        }

        return $response;
    }
}