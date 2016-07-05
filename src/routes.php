<?php
use \Psr\Http\Message\ServerRequestInterface as Request;
use \Psr\Http\Message\ResponseInterface as Response;

// Routes

$app->get('/', function($request, $response, $args) {
    // Sample log message
    $this->logger->info("Slim-Skeleton '/' route");

    // Render index view
    return $this->renderer->render($response, 'index.phtml', $args);
});

/**
 * GET request for the clubs list
 */
$app->get('/clubs', function(Request $request, Response $response) {
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
        array_push($data, createClubArray($club));
    }

    $newResponse = $response->withJson($data);

    return $newResponse;
});

/**
 * GET request for a specific club
 */
$app->get('/club/{id}', function(Request $request, Response $response) {
    $clubId = $request->getAttribute('id');
    $lang = getLang($request);

    $query = "SELECT cl.id, cl.nbIdClub, cl.club AS name, cl.nomComplet AS fullName, cl.nomPourTri AS sortingName, cl.actif,
                     cl.adresse AS clubAddress, cl.npa AS clubPostalCode, cl.ville AS clubCity, cl.email AS clubEmail, cl.telephone AS clubPhoneNumber,
                     p.nom AS presidentLastName, p.prenom AS presidentFirstName,
                     p.adresse AS presidentAddress, p.npa AS presidentPostalCode, p.ville AS presidentCity, p.email AS presidentEmail, p.telPrive AS presidentPhoneNumber, p.portable AS presidentMobileNumber,
                     cl.url, cl.facebookUsername, cl.twitterUsername, cl.flickrUsername, cl.canton AS cantonId, ca.sigle, ca.nomCanton$lang AS cantonName,
                     ccpc.idCategorie AS championshipCategory, ccpc.nbPlaces AS championshipNbSpots
              FROM ClubsFstb cl
              LEFT OUTER JOIN DBDPersonne p ON p.idDbdPersonne = cl.idPresident
              LEFT OUTER JOIN Canton ca ON ca.id = cl.canton
              LEFT OUTER JOIN Championnat_Clubs_Places_Categories ccpc ON ccpc.idClub = cl.id
              WHERE cl.id = :clubId";

    $result = $this->db->prepare($query);
    $result->execute(array(':clubId' => $clubId));
    $club = $result->fetch(PDO::FETCH_ASSOC);

    $data = createClubArray($club);

    $data['actif'] = $club['actif'] == 1;

    //TODO: It might be better to have a separate query in here to get the championshipSpots.
    $data['championshipSpots'] = array();
    array_push($data['championshipSpots'], createChampionshipSpot($club));

    // If they have spots in other categories, there will be more rows
    while ($row = $result->fetch(PDO::FETCH_ASSOC)) {
        array_push($data['championshipSpots'], createChampionshipSpot($row));
    }

    $newResponse = $response->withJson($data);

    return $newResponse;
});

/**
 * GET request for the members list of a specific club
 */
$app->get('/club/{id}/members', function(Request $request, Response $response) {
    $clubId = $request->getAttribute('id');
    $requestParams = $request->getQueryParams();
    $searchedTerm = $requestParams['query'];

    $query = "SELECT p.idDbdPersonne AS id,
                    p.nom AS lastName,
                    p.prenom AS firstName,
                    CONCAT(p.prenom, ' ', p.nom) AS fullName,
                    p.email,
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


});

/**
 * GET request for the list of open categories by season
 * TODO: make it possible to get the closed categories by season with a parameter in the request
 */
$app->get('/championship/categories-by-season', function(Request $request, Response $response) {
    $lang = getLang($request);

    $query = "SELECT ccps.id,
                     ccps.season,
                     cc.idCategorie AS categoryId,
                     cc.categorie$lang AS categoryName,
                     ccps.teamRegistrationFee,
                     ccps.playerLicenseFee,
                     ccps.refereeDefrayalAmount,
                     DATE_FORMAT(ccps.deadline, '%Y-%m-%dT%H:%i:%sZ') AS deadline
              FROM Championnat_Categories_Par_Saison ccps, Championnat_Categories cc
              WHERE TIMESTAMP(ccps.deadline) > NOW()
              AND ccps.categoryId = cc.idCategorie";

    try {
        $result = $this->db->prepare($query);
    } catch (PDOException $e) {
        return $response->withStatus(500)
            ->withHeader('Content-Type', 'text/html')
            ->write($e);
    }

    $result->execute();

    $data = array();
    while ($registration = $result->fetch(PDO::FETCH_ASSOC)) {
        array_push($data, array(
            'id' => intval($registration['id']),
            'season' => intval($registration['season']),
            'category' => array(
                'id' => intval($registration['categoryId']),
                'name' => $registration['categoryName']
            ),
            'teamRegistrationFee' => intval($registration['teamRegistrationFee']),
            'playerLicenseFee' => intval($registration['playerLicenseFee']),
            'refereeDefrayalAmount' => intval($registration['refereeDefrayalAmount']),
            'deadline' => $registration['deadline']
        ));
    }

    $newResponse = $response->withJson($data);

    return $newResponse;
});

/**
 * GET request to search for venues
 */
$app->get('/venues', function(Request $request, Response $response) {
    $requestParams = $request->getQueryParams();
    $searchedTerm = $requestParams['query'];
    $query = "SELECT id, nom AS name, ville AS city
              FROM Lieux
              WHERE nom LIKE :searchedTerm";
    $result = $this->db->prepare($query);
    $result->execute(array(':searchedTerm' => "%$searchedTerm%"));

    $data = array();
    while ($row = $result->fetch(PDO::FETCH_ASSOC)) {
        $venue = $row;
        $venue['id'] = intval($venue['id']);
        array_push($data, $venue);
    }

    $newResponse = $response->withJson($data);

    return $newResponse;
});

/**
 * POST request to add a team registration
 */
$app->post('/championship/register-team', function(Request $request, Response $response) {
    $registration = $request->getParsedBody();
    
    // TODO: Check that the club can register a team for this category.

    // Getting the user ID of the person who filled the form
    try {
        $userId = getUserIdFromUsername($this->db, $_SESSION['__username__']);
    } catch (PDOException $e) {
        return $response->withStatus(500)
                        ->withHeader('Content-Type', 'text/html')
                        ->write($e);
    }

    // Saving the team in the database
    $queryTeam = "INSERT INTO Championnat_Equipes (
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
        $this->db->exec($queryTeam);
        $newResponse = $response;
    } catch (PDOException $e) {
        $newResponse = $response->withStatus(500)
                                ->withHeader('Content-Type', 'text/html')
                                ->write($e);
    } finally {
        return $newResponse;
    }


});