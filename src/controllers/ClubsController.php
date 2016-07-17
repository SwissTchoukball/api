<?php

/*
 * @copyright   Swiss Tchoukball 2016
 * @author      David Sandoz <david.sandoz@tchoukball.ch>
 */

use \Psr\Http\Message\ServerRequestInterface as Request;
use \Psr\Http\Message\ResponseInterface as Response;
use Interop\Container\ContainerInterface as ContainerInterface;

class Clubs {
    protected $ci;

    // Constructor
    public function __construct(ContainerInterface $ci) {
        $this->ci = $ci;
        $this->db = $ci['db'];
    }

    /**
     * Get all the active clubs
     *
     * @param Request $request
     * @param Response $response
     * @return mixed
     */
    public function getClubs(Request $request, Response $response) {
        $query = "SELECT cl.id, cl.nbIdClub, cl.club AS name, cl.nomComplet AS fullName, cl.nomPourTri AS sortingName,
                     cl.adresse AS clubAddress, cl.npa AS clubPostalCode, cl.ville AS clubCity, cl.email AS clubEmail, cl.telephone AS clubPhoneNumber,
                     p.nom AS presidentLastName, p.prenom AS presidentFirstName,
                     p.adresse AS presidentAddress, p.npa AS presidentPostalCode, p.ville AS presidentCity, p.email AS presidentEmail, p.telPrive AS presidentPhoneNumber, p.portable AS presidentMobileNumber,
                     cl.url, cl.facebookUsername, cl.twitterUsername, cl.flickrUsername, cl.canton AS cantonId, ca.sigle, ca.nomCantonEn, ca.nomCantonFr, ca.nomCantonDe, ca.nomCantonIt
              FROM ClubsFstb cl
              LEFT OUTER JOIN DBDPersonne p ON p.idDbdPersonne = cl.idPresident
              LEFT OUTER JOIN Canton ca ON ca.id = cl.canton
              WHERE cl.actif = 1
              ORDER BY cl.nomPourTri";

        $result = $this->db->prepare($query);
        $result->execute();

        $data = array();
        while ($club = $result->fetch(PDO::FETCH_ASSOC)) {
            array_push($data, $this->_createClubArray($club));
        }

        $newResponse = $response->withJson($data);

        return $newResponse;
    }

    /**
     * Get a specific club
     *
     * @param Request $request
     * @param Response $response
     * @return mixed
     */
    public function getClub(Request $request, Response $response) {
        $clubId = $request->getAttribute('clubId');
        $lang = getLang($request);

        $query = "SELECT cl.id, cl.nbIdClub, cl.club AS name, cl.nomComplet AS fullName, cl.nomPourTri AS sortingName, cl.actif,
                     cl.adresse AS clubAddress, cl.npa AS clubPostalCode, cl.ville AS clubCity, cl.email AS clubEmail, cl.telephone AS clubPhoneNumber,
                     p.nom AS presidentLastName, p.prenom AS presidentFirstName,
                     p.adresse AS presidentAddress, p.npa AS presidentPostalCode, p.ville AS presidentCity, p.email AS presidentEmail, p.telPrive AS presidentPhoneNumber, p.portable AS presidentMobileNumber,
                     cl.url, cl.facebookUsername, cl.twitterUsername, cl.flickrUsername, cl.canton AS cantonId, ca.sigle, ca.nomCanton$lang AS cantonName,
                     ccpc.idCategorie AS championshipCategoryId, ccpc.nbPlaces AS championshipNbSpots
              FROM ClubsFstb cl
              LEFT OUTER JOIN DBDPersonne p ON p.idDbdPersonne = cl.idPresident
              LEFT OUTER JOIN Canton ca ON ca.id = cl.canton
              LEFT OUTER JOIN Championnat_Clubs_Places_Categories ccpc ON ccpc.idClub = cl.id
              WHERE cl.id = :clubId";

        $result = $this->db->prepare($query);
        $result->execute(array(':clubId' => $clubId));
        $club = $result->fetch(PDO::FETCH_ASSOC);

        $data = $this->_createClubArray($club);

        $data['actif'] = $club['actif'] == 1;

        //TODO: It might be better to have a separate query in here to get the championshipSpots.
        $data['championshipSpots'] = array();
        array_push($data['championshipSpots'], $this->_createChampionshipSpot($club));

        // If they have spots in other categories, there will be more rows
        while ($row = $result->fetch(PDO::FETCH_ASSOC)) {
            array_push($data['championshipSpots'], $this->_createChampionshipSpot($row));
        }

        $newResponse = $response->withJson($data);

        return $newResponse;
    }

    /**
     * Get members of a specific club
     *
     * @param Request $request
     * @param Response $response
     * @return mixed
     */
    public function getMembers(Request $request, Response $response) {
        $clubId = $request->getAttribute('clubId');
        $requestParams = $request->getQueryParams();
        $searchedTerm = $requestParams['query'];

        $query = "SELECT p.idDbdPersonne AS id,
                    p.nom AS lastName,
                    p.prenom AS firstName,
                    CONCAT(p.prenom, ' ', p.nom) AS fullName,
                    p.email,
                    p.emailFSTB,
                    p.telPrive AS phoneNumber,
                    p.portable AS mobileNumber
             FROM DBDPersonne p, ClubsFstb c
             WHERE c.id = :clubId
             AND p.idClub = c.nbIdClub
             AND (p.nom LIKE :searchedTerm OR p.prenom LIKE :searchedTerm)";

        $result = $this->db->prepare($query);
        $result->execute(array(':clubId' => $clubId, ':searchedTerm' => "%$searchedTerm%"));

        $data = array();
        while ($row = $result->fetch(PDO::FETCH_ASSOC)) {
            $person = $row;
            $person['id'] = intval($person['id']);
            array_push($data, $person);
        }

        $newResponse = $response->withJson($data);

        return $newResponse;
    }

    /**
     * Get teams of a specific club
     *
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function getTeams(Request $request, Response $response) {
        $clubId = $request->getAttribute('clubId');
        $lang = getLang($request);

        $query = "SELECT ce.idEquipe AS id,
                     ce.equipe AS name,
                     ccps.id AS categoryBySeasonId,
                     ccps.season,
                     cc.idCategorie AS categoryId,
                     cc.categorie$lang AS categoryName,
                     cl.id AS clubId,
                     cl.club AS clubName,
                     ce.feePaymentDate
              FROM Championnat_Equipes ce,
                   Championnat_Categories_Par_Saison ccps,
                   Championnat_Categories cc,
                   ClubsFstb cl
              WHERE ce.idCategorieParSaison = ccps.id
              AND ccps.categoryId = cc.idCategorie
              AND ce.idClub = cl.id
              AND cl.id = :clubId
              ORDER BY registrationDate DESC";

        $result = $this->db->prepare($query);
        $result->execute(array(':clubId' => $clubId));

        // TODO: use a model
        $data = array();
        while ($team = $result->fetch(PDO::FETCH_ASSOC)) {
            array_push($data, array(
                'id' => intval($team['id']),
                'name' => $team['name'],
                'club' => array(
                    'id' => intval($team['clubId']),
                    'name' => $team['clubName']
                ),
                'categoryBySeasonId' => intval($team['categoryBySeasonId']),
                'season' => array(
                    'startYear' => intval($team['season']),
                    'name' => getSeasonName($team['season'])
                ),
                'category' => array(
                    'id' => intval($team['categoryId']),
                    'name' => $team['categoryName']
                ),
                'feePaymentDate' => $team['feePaymentDate']
            ));
        }

        $newResponse = $response->withJson($data);

        return $newResponse;
    }

    private function _createClubArray($club) {
        // Defining postal address
        $address = array();
        if (strlen($club["clubPostalCode"]) == 4 && strlen($club["clubCity"]) >= 3) {
            $address['firstLine'] = $club["clubAddress"];
            $address['postalCode'] = intval($club["clubPostalCode"]);
            $address['city'] = $club["clubCity"];
        } else {
            $address['firstLine'] = stripslashes($club["presidentFirstName"]) . " " . stripslashes($club["presidentLastName"]);
            $address['secondLine'] = $club["presidentAddress"];
            $address['postalCode'] = intval($club["presidentPostalCode"]);
            $address['city'] = $club["presidentCity"];
        }

        // Defining email address
        if ($club['clubEmail'] != "") {
            $email = $club["clubEmail"];
        } else if ($club["presidentEmail"] != "") {
            $email = $club["presidentEmail"];
        } else {
            $email = '';
        }

        // Defining phone number
        if ($club['clubPhoneNumber'] != "") {
            $phoneNumber = $club['clubPhoneNumber'];
            $mobileNumber = '';
        } else {
            if ($club["presidentPhoneNumber"] != "") {
                $phoneNumber = $club['presidentPhoneNumber'];
            } else {
                $phoneNumber = '';
            }
            if ($club["presidentMobileNumber"] != "") {
                $mobileNumber = $club['presidentMobileNumber'];
            } else {
                $mobileNumber = '';
            }
        }

        return array(
            'id' => intval($club['id']),
            'nbIdClub' => intval($club['nbIdClub']),
            'name' => $club['name'],
            'fullName' => $club['fullName'],
            'sortingName' => $club['sortingName'],
            'canton' => array(
                'id' => intval($club['cantonId']),
                'acronym' => $club['sigle'],
                'name' => $club['cantonName']
            ),
            'address' => $address,
            'email' => $email,
            'phoneNumber' => $phoneNumber,
            'mobileNumber' => $mobileNumber,
            'url' => $club['url'],
            'usernames' => array(
                'facebook' => $club['facebookUsername'],
                'twitter' => $club['twitterUsername'],
                'flickr' => $club['flickrUsername'],
            )
        );
    }

    private function _createChampionshipSpot($club) {
        return array(
            'categoryId' => intval($club['championshipCategoryId']),
            'nbSpots' => intval($club['championshipNbSpots'])
        );
    }
}