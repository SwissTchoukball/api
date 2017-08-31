<?php
/**
 * @copyright   Swiss Tchoukball 2016
 * @author      David Sandoz <david.sandoz@tchoukball.ch>
 *
 * TODO: distribute helpers method in different files
 */

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

function getClubOfLicense($db, $licenseId) {
    $query = "SELECT ce.idClub as clubId
              FROM Championnat_Equipes ce, Championnat_Joueurs cj
              WHERE ce.idEquipe = cj.teamId
              AND cj.id = :licenseId
              LIMIT 1";
    try {
        $result = $db->prepare($query);
        $result->execute(array(':licenseId' => $licenseId));
    } catch (PDOException $e) {
        throw $e;
    }

    $data = $result->fetch(PDO::FETCH_ASSOC);

    return $data['clubId'];
}

function getSeasonName($startYear) {
    return $startYear . ' - ' . ($startYear + 1);
}

function hasClubReadAccess($user, $clubId) {
    return $clubId == $user['clubId'] &&
    isset($user['rights']['club']) &&
    $user['rights']['club']['read'];
}

function hasClubMembersReadAccess($user, $clubId) {
    return $clubId == $user['clubId'] &&
    isset($user['rights']['clubMembers']) &&
    $user['rights']['clubMembers']['read'];
}

function hasClubFinancesReadAccess($user, $clubId) {
    return $clubId == $user['clubId'] &&
    isset($user['rights']['clubFinances']) &&
    $user['rights']['clubFinances']['read'];
}

function hasClubTeamsReadAccess($user, $clubId) {
    return $clubId == $user['clubId'] &&
    isset($user['rights']['clubTeams']) &&
    $user['rights']['clubTeams']['read'];
}

function hasClubTeamsWriteAccess($user, $clubId) {
    return $clubId == $user['clubId'] &&
    isset($user['rights']['clubTeams']) &&
    $user['rights']['clubTeams']['write'];
}

function hasTeamsReadAccess($user) {
    return isset($user['rights']['teams']) && $user['rights']['teams']['read'];
}

function hasFinancesReadAccess($user) {
    return isset($user['rights']['finances']) && $user['rights']['finances']['read'];
}

function hasChampionshipReadAccess($user) {
    return isset($user['rights']['championship']) && $user['rights']['championship']['read'];
}

function hasChampionshipWriteAccess($user) {
    return isset($user['rights']['championship']) && $user['rights']['championship']['write'];
}