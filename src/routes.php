<?php

function goToSwissTchoukballWebsite() {
    //Redirect to tchoukball.ch
    header("Location: https://tchoukball.ch", true, 301);
    exit();
}
// Routes

// GET requests
//$app->get('/', 'goToSwissTchoukballWebsite');
$app->get('/clubs', '\Clubs:getClubs');
$app->get('/club/{clubId}', '\Clubs:getClub');
$app->get('/club/{clubId}/members', '\Clubs:getMembers');
$app->get('/club/{clubId}/teams', '\Clubs:getTeams');
$app->get('/club/{clubId}/team/{teamId}', '\Clubs:getTeam'); // Could be moved in a Team controller and check if clubId is given to return sensitive information (payment date)
$app->get('/championship/categories-by-season', '\Championship:getCategoriesBySeason');
$app->get('/venues', '\Venues:getVenues');

// POST requests
$app->post('/championship/register-team', '\Championship:registerTeam');
$app->post('/championship/register-players', '\Championship:registerPlayers');