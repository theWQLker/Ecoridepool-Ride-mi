<?php

use Slim\App;
use Slim\Views\Twig;
use App\Controllers\UserController;
use App\Controllers\CarpoolController;
use App\Controllers\DriverController;
use App\Controllers\RideController;
use App\Controllers\AdminController;
use App\Controllers\ReviewController;
use App\Controllers\EmployeeController;
use App\Controllers\ProfileController;
use MongoDB\Client as MongoDBClient;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface     as Response;

return function (App $app) {
    $container = $app->getContainer();
    $twig = Twig::create(__DIR__ . '/../app/templates');
    $twig = $container->get('view');

    if (session_status() === PHP_SESSION_NONE) session_start();

    // =========================
    // HOME & LANDING – Page d'accueil générale
    // =========================
    $app->get('/', function ($request, $response) use ($twig) {
        if (isset($_SESSION['user']) && $_SESSION['user']['role'] === 'admin') {
            return $response->withHeader('Location', '/admin')->withStatus(302);
        }
        return $twig->render($response, 'home.twig', ['user' => $_SESSION['user'] ?? null]);
    });

    // =========================
    // AUTHENTICATION
    // =========================
    $app->get('/login', fn($req, $res) => Twig::fromRequest($req)->render($res, 'login.twig'));
    $app->post('/login', [UserController::class, 'login']);
    $app->get('/logout', [UserController::class, 'logout']);
    $app->post('/logout', [UserController::class, 'logout']);

    // =========================
    // REGISTRATION
    // =========================
    // =========================
    // REGISTRATION (GET)
    // =========================
    $app->get('/register', function (Request $req, Response $res) use ($twig) {
        // Slim-CSRF’s default key names:
        $nameKey  = 'csrf_name';
        $valueKey = 'csrf_value';

        // These attributes were populated on the Request by the CSRF guard
        $nameVal  = $req->getAttribute($nameKey);
        $valueVal = $req->getAttribute($valueKey);

        return $twig->render($res, 'register.twig', [
            'csrf' => [
                'keys'  => ['name' => $nameKey, 'value' => $valueKey],
                'name'  => $nameVal,
                'value' => $valueVal,
            ],
        ]);
    });
    $app->post('/register', [UserController::class, 'register']);
    $app->post('/register-driver', [DriverController::class, 'registerDriver']);

    // =========================
    // PROFILE MANAGEMENT
    // =========================
    $app->get('/profile', [ProfileController::class, 'show']);
    $app->post('/profile', [ProfileController::class, 'update']);

    // =========================
    // DRIVER ROUTES
    // =========================
    $app->group('/driver', function ($group) {
        $group->get('/dashboard', [RideController::class, 'getDriverRideHistory']);
        $group->get('/carpools/create', CarpoolController::class . ':createForm');
        $group->post('/carpools', CarpoolController::class . ':storeCarpool');
        $group->post('/carpools/{id}/start', [CarpoolController::class, 'startCarpool']);
        $group->post('/carpools/{id}/complete', [CarpoolController::class, 'completeCarpool']);
        $group->put('/complete-ride/{id}', [RideController::class, 'completeRide']);
        $group->put('/cancel-ride/{id}', [RideController::class, 'cancelRide']);
    });

    // =========================
    // PASSENGER ROUTES
    // =========================
    $app->get('/rides', [RideController::class, 'getPassengerRideHistory']);
    $app->put('/complete-ride/{id}', [RideController::class, 'completeRide']);
    $app->put('/cancel-ride/{id}', [RideController::class, 'cancelRide']);
    $app->post('/cancel-ride/{id}', [RideController::class, 'cancelRide']);

    // =========================
    // CARPOOL BROWSING (PASSENGER)
    // =========================
    $app->get('/carpools', CarpoolController::class . ':listAvailable');
    $app->get('/carpools/{id}', CarpoolController::class . ':viewDetail');
    $app->post('/carpools/{id}/join', CarpoolController::class . ':joinCarpool');

    // =========================
    // REVIEW & DISPUTES
    // =========================
    $app->get('/review/{id}', [ReviewController::class, 'showReviewForm']);
    $app->post('/review/submit', [ReviewController::class, 'submit']);
    $app->post('/dispute/{id}', [ReviewController::class, 'dispute']);

    // =========================
    // EMPLOYEE (MODERATION PANEL)
    // =========================
    $app->group('/employee', function ($group) {
        $group->get('', EmployeeController::class . ':index');
        $group->post('/resolve/{id}', EmployeeController::class . ':resolve');
        $group->get('/dispute/{id}', EmployeeController::class . ':viewDispute');
        $group->map(['POST', 'DELETE'], '/reviews/delete/{id}', [ReviewController::class, 'delete']);
        $group->post('/reviews/{id}/approve', EmployeeController::class . ':approveReview');
        $group->post('/reviews/{id}/reject', EmployeeController::class . ':rejectReview');
    });

    // =========================
    // ADMIN PANEL
    // =========================
    $app->group('/admin', function ($group) {
        $group->get('', [AdminController::class, 'dashboard']);
        $group->put('/update-user/{id}', [AdminController::class, 'updateUser']);
        $group->delete('/delete-user/{id}', [AdminController::class, 'deleteUser']);
        $group->delete('/delete-ride/{id}', [AdminController::class, 'deleteRide']);
        $group->post('/user/{id}/suspend', AdminController::class . ':suspendUser');
        $group->get('/graph-data', AdminController::class . ':getGraphData');
    });

    // =========================
    // STATIC PAGES
    // =========================
    $app->get('/menu', fn($req, $res) => $container->get('view')->render($res, 'menu.twig', ['user' => $_SESSION['user'] ?? null]));
    $app->get('/maps/route', [RideController::class, 'getRouteData']);
    $app->get('/legal', fn($req, $res) => $container->get('view')->render($res, 'legal.twig'));
};
