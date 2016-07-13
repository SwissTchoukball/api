<?php

/*
 * @copyright   Swiss Tchoukball 2016
 * @author      David Sandoz <david.sandoz@tchoukball.ch>
 */

use \Psr\Http\Message\ServerRequestInterface as Request;
use \Psr\Http\Message\ResponseInterface as Response;
use Interop\Container\ContainerInterface as ContainerInterface;

class Venues {
    protected $ci;

    // Constructor
    public function __construct(ContainerInterface $ci) {
        $this->ci = $ci;
        $this->db = $ci['db'];
    }

    /**
     * Get all the venues
     *
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function getVenues(Request $request, Response $response) {
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
    }
}