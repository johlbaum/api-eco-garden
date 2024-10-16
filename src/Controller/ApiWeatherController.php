<?php

namespace App\Controller;

use App\Entity\User;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class ApiWeatherController extends AbstractController
{
    private string $apiKey;

    public function __construct()
    {
        $this->apiKey = $_ENV['OPENWEATHER_API_KEY'];
    }

    /**
     * Permet de retourner la météo d’une ville donnée. 
     */
    #[Route('/api/meteo/{ville}', name: 'app_getWeatherByTown', methods: ['GET'])]
    public function getWeatherByTown(string $ville, HttpClientInterface $httpClient): JsonResponse
    {
        return $this->fetchWeatherData($ville, $httpClient);
    }

    /**
     * Permet de retourner la météo de la ville de l'utilisateur authentifié.
     */
    #[Route('/api/meteo', name: 'app_getWeatherByUserTown', methods: ['GET'])]
    public function getWeatherByUserTown(HttpClientInterface $httpClient): JsonResponse
    {
        $user = $this->getUser();

        if (!$user instanceof User) {
            return new JsonResponse(['error' => 'User not authenticated'], 401);
        }

        $userTown = $user->getTown();
        return $this->fetchWeatherData($userTown, $httpClient);
    }

    /**
     * Permet de récupèrer les données météo d'une ville donnée via l'API OpenWeather.
     */
    private function fetchWeatherData(string $town, HttpClientInterface $httpClient): JsonResponse
    {
        $response = $httpClient->request(
            'GET',
            "https://api.openweathermap.org/data/2.5/weather?q={$town}&appid=" . $this->apiKey // Utilisation de $this->apiKey
        );

        if ($response->getStatusCode() !== 200) {
            return new JsonResponse(['error' => 'Weather data not found'], $response->getStatusCode());
        }

        // On convertit le contenu de la réponse JSON renvoyée par l'API en un tableau associatif.
        $weatherData = json_decode($response->getContent(), true);

        if (isset($weatherData['weather']) && !empty($weatherData['weather'])) {
            $weatherDescription = $weatherData['weather'][0]['description'];
        } else {
            return new JsonResponse(['error' => 'Weather data not found'], 404);
        }

        $responseData = [
            'city' => $town,
            'weather' => $weatherDescription,
        ];

        return new JsonResponse($responseData, 200);
    }
}
