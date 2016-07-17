<?php

/*
 * @copyright   Swiss Tchoukball 2016
 * @author      David Sandoz <david.sandoz@tchoukball.ch>
 */

namespace SwissTchoukball\Middleware;

use \Psr\Http\Message\ServerRequestInterface as Request;
use \Psr\Http\Message\ResponseInterface as Response;
use Interop\Container\ContainerInterface as ContainerInterface;

class Authorization {
    protected $ci;

    public function __construct(ContainerInterface $ci)
    {
        $this->user = $ci['user'];
    }

    public function __invoke(Request $request, Response $response, callable $next) {
        $path = explode('/', $request->getUri()->getPath());
        $method = $request->getMethod();
        if ($path[1] == 'club' && $method == 'GET') {
            // Authorization to read a specific club data
            if (isset($request->getAttribute('routeInfo')[2]['clubId'])) {
                $clubId = $request->getAttribute('routeInfo')[2]['clubId'];

                if (!hasClubReadAccess($this->user, $clubId)) {
                    return $response->withStatus(403);
                }

                if (!array_key_exists(3, $path)) {
                    // There is no path after the clubId, so this is just to get club information
                    $response = $next($request, $response);
                }
                else if ($path[3] == 'members') {
                    if (!hasClubMembersReadAccess($this->user, $clubId)) {
                        return $response->withStatus(403);
                    }

                    $response = $next($request, $response);
                }
                else if ($path[3] == 'teams') {
                    if (!hasClubTeamsReadAccess($this->user, $clubId)) {
                        return $response->withStatus(403);
                    }

                    $response = $next($request, $response);
                }
                else {
                    // If the path continue after the clubId but was not caught previously,
                    // we don't know the path, so we don't give access
                    return $response->withStatus(403);
                }
            }
            else {
                // If no clubId is given
                $response = $response->withStatus(400);
            }
        }
        else if ($path[1] == 'championship' && $method == 'GET') {
            if (!hasTeamsReadAccess($this->user) &&
                !hasChampionshipReadAccess($this->user)) {
                return $response->withStatus(403);
            }

            $response = $next($request, $response);
        }
        else if ($path[1] == 'championship' && $method == 'POST') {
            $registration = $request->getParsedBody();
            if (!hasClubTeamsWriteAccess($this->user, $registration['clubId']) &&
                !hasChampionshipWriteAccess($this->user)) {
                return $response->withStatus(403);
            }

            $response = $next($request, $response);
        }
        else {
            // We authorize any other path
            $response = $next($request, $response);
        }
        return $response;
    }
}
