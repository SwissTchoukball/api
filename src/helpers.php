<?php

// TODO distribute helpers method in different files

function createClubArray($club) {
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

function createChampionshipSpot($club) {
    return array(
        'categoryId' => intval($club['championshipCategoryId']),
        'nbSpots' => intval($club['championshipNbSpots'])
    );
}

function getLang($request) {
    $lang = 'Fr';
    if ($request->hasHeader('Accept-Language')) {
        $headerLang = $request->getHeaderLine('Accept-Language');
        if (strlen($headerLang) == 2) {
            $lang = ucfirst($headerLang);
        }
    }
    
    return $lang;
}

function getUsers($db) {
    $query = "SELECT username, password
              FROM Personne";
    $result = $db->prepare($query);
    $result->execute();

    $users = array();
    while ($user = $result->fetch(PDO::FETCH_ASSOC)) {
        $users[$user['username']] = $user['password'];
    }
    
    return $users;
}

function getUserByUsername($db, $username) {
    $userQuery = "SELECT p.id, p.username, p.idClub AS clubId, CONCAT(p.prenom, ' ', p.nom) AS fullName
              FROM Personne p
              WHERE p.username = '$username'";
    try {
        $userResult = $db->prepare($userQuery);
        $userResult->execute();
    } catch (PDOException $e) {
        throw $e;
    }

    $user = $userResult->fetch(PDO::FETCH_ASSOC);

    $rightsQuery = "SELECT ass.name AS asset, MAX(ri.read) AS `read`, MAX(ri.write) AS `write`
                    FROM acm_rights ri, acm_roles ro, acm_assets ass, acm_distribution dis
                    WHERE dis.user_id = {$user['id']}
                    AND dis.role_id = ri.role_id
                    AND ri.asset_id = ass.id
                    GROUP BY asset";
    try {
        $rightsResult = $db->prepare($rightsQuery);
        $rightsResult->execute();
    } catch (PDOException $e) {
        throw $e;
    }

    $user['rights'] = array();
    while ($right = $rightsResult->fetch(PDO::FETCH_ASSOC)) {
        $user['rights'][$right['asset']]['read'] = $right['read'] == 1;
        $user['rights'][$right['asset']]['write'] = $right['write'] == 1;
    }

    return $user;
    
}

function getSeasonName($startYear) {
    return $startYear . ' - ' . ($startYear + 1);
}

function hasClubMembersReadAccess($user, $clubId) {
    return $clubId == $user['clubId'] && $user['rights']['clubMembers']['read'];
}