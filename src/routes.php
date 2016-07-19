<?php

function goToSwissTchoukballWebsite() {
    //Redirect to tchoukball.ch
    header("Location: https://tchoukball.ch", true, 301);
    exit();
}
// Routes

// GET requests
$app->get('/', 'goToSwissTchoukballWebsite');
$app->get('/clubs', '\Clubs:getClubs');
$app->get('/club/{clubId}', '\Clubs:getClub');
$app->get('/club/{clubId}/members', '\Clubs:getMembers');
$app->get('/club/{clubId}/teams', '\Clubs:getTeams');
$app->get('/championship/editions', '\Championship:getEditions');
$app->get('/championship/team/{teamId}', '\Championship:getTeam');
$app->get('/championship/teams', '\Championship:getTeams');
$app->get('/venues', '\Venues:getVenues');

// POST requests
$app->post('/championship/register-team', '\Championship:registerTeam');
$app->post('/championship/register-players', '\Championship:registerPlayers');