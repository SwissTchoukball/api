<?php

/*
 * @copyright   Swiss Tchoukball 2016
 * @author      David Sandoz <david.sandoz@tchoukball.ch>
 */

use \Psr\Http\Message\ServerRequestInterface as Request;
use \Psr\Http\Message\ResponseInterface as Response;
use Interop\Container\ContainerInterface as ContainerInterface;

class Championship {
    protected $ci;

    // Constructor
    public function __construct(ContainerInterface $ci) {
        $this->ci = $ci;
        $this->db = $ci['db'];
        $this->user = $ci['user'];
        $this->mailer = $ci['mailer'];
        $this->emailAddresses = $ci['emailAddresses'];
    }

    /**
     * Get the list of categories by season
     *
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function getEditions(Request $request, Response $response) {
        $lang = getLang($request);
        $params = $request->getQueryParams();

        $query = "SELECT ced.id,
                     ced.season,
                     cc.idCategorie AS categoryId,
                     cc.categorie$lang AS categoryName,
                     cc.isNbSpotLimitedByClub,
                     ced.teamRegistrationFee,
                     ced.playerLicenseFee,
                     ced.refereeDefrayalAmount,
                     DATE_FORMAT(ced.registrationDeadline, '%Y-%m-%dT%H:%i:%s.000Z') AS registrationDeadline,
                     DATE_FORMAT(ced.paymentDeadline, '%Y-%m-%dT%H:%i:%s.000Z') AS paymentDeadline
              FROM Championnat_Editions ced, Championnat_Categories cc
              WHERE ced.categoryId = cc.idCategorie";

        if (isset($params['status']) && $params['status'] == 'open') {
            $query .= " AND TIMESTAMP(ced.registrationDeadline) > NOW()";
        }

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
                'registrationDeadline' => $registration['registrationDeadline'],
                'paymentDeadline' => $registration['paymentDeadline']
            ));
        }

        $newResponse = $response->withJson($data);

        return $newResponse;
    }

    public function getTeams(Request $request, Response $response) {
        $lang = getLang($request);

        $query = "SELECT ce.idEquipe AS id,
                     ce.equipe AS name,
                     ced.id AS editionId,
                     ced.season,
                     cc.idCategorie AS categoryId,
                     cc.categorie$lang AS categoryName,
                     cl.id AS clubId,
                     cl.club AS clubName,
                     ce.feePaymentDate
              FROM Championnat_Equipes ce,
                   Championnat_Editions ced,
                   Championnat_Categories cc,
                   ClubsFstb cl
              WHERE ce.idEdition = ced.id
              AND ced.categoryId = cc.idCategorie
              AND ce.idClub = cl.id
              ORDER BY season DESC, categoryId, cl.nomPourTri, name";

        try {
            $result = $this->db->prepare($query);
            $result->execute();
        } catch (PDOException $e) {
            return $response->withStatus(500)
                ->withHeader('Content-Type', 'text/html')
                ->write($e);
        }

        // TODO: use a model
        $data = array();
        while ($team = $result->fetch(PDO::FETCH_ASSOC)) {
            $returnedTeam = array(
                'id' => intval($team['id']),
                'name' => $team['name'],
                'club' => array(
                    'id' => intval($team['clubId']),
                    'name' => $team['clubName']
                ),
                'editionId' => intval($team['editionId']),
                'season' => array(
                    'startYear' => intval($team['season']),
                    'name' => getSeasonName($team['season'])
                ),
                'category' => array(
                    'id' => intval($team['categoryId']),
                    'name' => $team['categoryName']
                )
            );

            if (hasClubFinancesReadAccess($this->user, $team['clubId']) ||
                hasClubTeamsReadAccess($this->user, $team['clubId']) ||
                hasFinancesReadAccess($this->user) ||
                hasChampionshipReadAccess($this->user)) {
                $returnedTeam['feePaymentDate'] = $team['feePaymentDate'];
            }

            array_push($data, $returnedTeam);
        }

        $newResponse = $response->withJson($data);

        return $newResponse;
    }

    public function getTeam(Request $request, Response $response) {
        $teamId = $request->getAttribute('teamId');
        $lang = getLang($request);

        // Loading team
        $teamQuery = "SELECT ce.idEquipe AS id,
                     ce.equipe AS name,
                     cl.id AS clubId,
                     cl.club AS clubName,
                     ced.id AS editionId,
                     ced.teamRegistrationFee,
                     ced.playerLicenseFee,
                     DATE_FORMAT(ced.registrationDeadline, '%Y-%m-%dT%H:%i:%s.000Z') AS registrationDeadline,
                     DATE_FORMAT(ced.paymentDeadline, '%Y-%m-%dT%H:%i:%s.000Z') AS paymentDeadline,
                     ced.season,
                     cc.idCategorie AS categoryId,
                     cc.categorie$lang AS categoryName,
                     ce.idResponsable AS managerId,
                     p.nom AS managerLastName,
                     p.prenom AS managerFirstName,
                     p.email AS managerEmail,
                     p.telPrive AS managerPhoneNumber,
                     p.portable AS managerMobileNumber,
                     ce.idLieuDomicile AS homeVenueId,
                     l.nom AS homeVenueName,
                     ce.couleurMaillotDomicile,
                     ce.couleurMaillotExterieur,
                     ce.feePaymentDate
              FROM Championnat_Equipes ce,
                   Championnat_Editions ced,
                   Championnat_Categories cc,
                   ClubsFstb cl,
                   DBDPersonne p,
                   Lieux l
              WHERE ce.idEdition = ced.id
              AND ced.categoryId = cc.idCategorie
              AND ce.idResponsable = p.idDbdPersonne
              AND ce.idLieuDomicile = l.id
              AND ce.idClub = cl.id
              AND ce.idEquipe = :teamId";

        $teamResult = $this->db->prepare($teamQuery);
        $teamResult->execute(array(':teamId' => $teamId));

        $team = $teamResult->fetch(PDO::FETCH_ASSOC);

        // Loading players
        $playersQuery = "SELECT p.idDbdPersonne AS id,
                                p.nom AS lastName,
                                p.prenom AS firstName,
                                cj.licensePaymentDate
                         FROM Championnat_Joueurs cj, DBDPersonne p
                         WHERE cj.personId = p.idDbdPersonne
                         AND cj.teamId = :teamId
                         ORDER BY lastName, firstName";

        $playersResult = $this->db->prepare($playersQuery);
        $playersResult->execute(array(':teamId' => $teamId));

        $players = array();
        while ($player = $playersResult->fetch(PDO::FETCH_ASSOC)) {
            $returnedPlayer = array(
                'id' => intval($player['id']),
                'lastName' => $player['lastName'],
                'firstName' => $player['firstName'],
                'licensePaymentDate' => $player['licensePaymentDate']
            );

            array_push($players, $returnedPlayer);
        }


        // TODO: use a model
        $data = array(
            'id' => intval($team['id']),
            'name' => $team['name'],
            'club' => array(
                'id' => intval($team['clubId']),
                'name' => $team['clubName']
            ),
            'edition' => array(
                'id' => intval($team['editionId']),
                'registrationDeadline' => $team['registrationDeadline'],
                'paymentDeadline' => $team['paymentDeadline'],
                'teamRegistrationFee' => intval($team['teamRegistrationFee']),
                'playerLicenseFee' => intval($team['playerLicenseFee']),
            ),
            'season' => array(
                'startYear' => intval($team['season']),
                'name' => getSeasonName($team['season'])
            ),
            'category' => array(
                'id' => intval($team['categoryId']),
                'name' => $team['categoryName']
            ),
            'manager' => array(
                'id' => intval($team['managerId']),
                'lastName' => $team['managerLastName'],
                'firstName' => $team['managerFirstName'],
                'email' => $team['managerEmail'],
                'phoneNumber' => $team['managerPhoneNumber'],
                'mobileNumber' => $team['managerMobileNumber']
            ),
            'homeVenue' => array(
                'id' => intval($team['homeVenueId']),
                'name' => $team['homeVenueName']
            ),
            'players' => $players
        );

        if (hasClubFinancesReadAccess($this->user, $team['clubId']) ||
            hasClubTeamsReadAccess($this->user, $team['clubId']) ||
            hasFinancesReadAccess($this->user) ||
            hasChampionshipReadAccess($this->user)) {
            $data['feePaymentDate'] = $team['feePaymentDate'];
        }

        $newResponse = $response->withJson($data);

        return $newResponse;
    }

    private function _registerPlayers(Array $playersId, $teamId) {

        // Saving the players in the database
        $playerQuery = "INSERT INTO Championnat_Joueurs (
                        teamId,
                        personId,
                        registrationAuthorId,
                        registrationDate
                    )
                    VALUES ";

        foreach ($playersId as $playerId) {
            $playerQuery .= "($teamId, $playerId, {$this->user['id']}, NOW()),";
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
     * 
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function registerTeam(Request $request, Response $response) {
        $registration = $request->getParsedBody();
        $clubId = $registration['clubId'];
        $editionId = $registration['editionId'];
        $teamName = $registration['teamName'];
        $teamManagerId = $registration['teamManagerId'];
        $jerseyColorHome = $registration['jerseyColorHome'];
        $jerseyColorAway = $registration['jerseyColorAway'];
        $homeVenueId = $registration['homeVenueId'];
        $playersId = $registration['playersId'];

        // Getting information regarding if a club can only register a limited number of team in a category
        $spotLimitationQuery = "SELECT c.isNbSpotLimitedByClub
                            FROM Championnat_Categories c, Championnat_Editions cps
                            WHERE c.idCategorie = cps.categoryId
                            AND cps.id = {$editionId}";
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
                                 WHERE idClub = {$clubId}
                                 AND idEdition = {$editionId}) AS nbRegisteredTeam,
                                (SELECT cpc.nbPlaces
                                 FROM Championnat_Clubs_Places_Categories cpc, Championnat_Editions cps
                                 WHERE cpc.idCategorie = cps.categoryId
                                 AND cpc.idClub = {$clubId}
                                 AND cps.id = {$editionId}) AS nbSpots";
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
                      idEdition,
                      couleurMaillotDomicile,
                      couleurMaillotExterieur,
                      idLieuDomicile,
                      registrationAuthorId,
                      registrationDate
                  )
                  VALUES (
                      '{$teamName}',
                      {$clubId},
                      {$teamManagerId},
                      {$editionId},
                      '{$jerseyColorHome}',
                      '{$jerseyColorAway}',
                      {$homeVenueId},
                      {$this->user['id']},
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
            $this->_registerPlayers($playersId, $teamId);
        } catch(PDOException $e) {
            return $response->withStatus(500)
                ->withHeader('Content-Type', 'text/html')
                ->write($e);
        }

        // Informing the head of finance and head of championship.
        $this->mailer->addAddress($this->emailAddresses['headOfChampionship']);
        $this->mailer->addAddress($this->emailAddresses['headOfFinances']);
        $this->mailer->Subject = 'Ajout d\'une équipe';
        // TODO: Give more informations
        $this->mailer->Body = 'Une équipe de ' . count($playersId) . ' joueurs a été ajoutée';

        if(!$this->mailer->send()) {
            //TODO: Define where we can log that.
            error_log('Message could not be sent.');
            error_log('Mailer Error: ' . $this->mailer->ErrorInfo);
        } else {
            error_log('Message has been sent');
        }

        return $response;
    }

    /**
     * Register players in a team
     * 
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function registerPlayers(Request $request, Response $response) {
        $registration = $request->getParsedBody();
        $playersId = $registration['playersId'];
        $teamId = $registration['teamId'];

        try {
            $this->_registerPlayers($playersId, $teamId);
        } catch(PDOException $e) {
            return $response->withStatus(500)
                ->withHeader('Content-Type', 'text/html')
                ->write($e);
        }

        // Informing the head of finance, head of championship and the developers
        $this->mailer->addAddress($this->emailAddresses['headOfChampionship']);
        $this->mailer->addAddress($this->emailAddresses['headOfFinances']);
        $this->mailer->addAddress($this->emailAddresses['developers']);
        $this->mailer->Subject = 'Ajout de ' . count($playersId) . ' joueur(s)';
        // TODO: Give more informations
        $this->mailer->Body = 'Joueur(s) ajouté(s)';

        if(!$this->mailer->send()) {
            //TODO: Define where we can log that.
            error_log('Message could not be sent.');
            error_log('Mailer Error: ' . $this->mailer->ErrorInfo);
        } else {
            error_log('Message has been sent');
        }

        return $response;
    }
}