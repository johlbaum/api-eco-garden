<?php

namespace App\Controller;

use App\Entity\User;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\Cache\TagAwareCacheInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class ApiWeatherController extends AbstractController
{
    private string $apiKey;
    private TagAwareCacheInterface $cachePool;

    public function __construct(TagAwareCacheInterface $cachePool)
    {
        $this->apiKey = $_ENV['OPENWEATHER_API_KEY'];
        $this->cachePool = $cachePool;
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
     * Permet de récupérer les données météo d'une ville donnée.
     */
    private function fetchWeatherData(string $town, HttpClientInterface $httpClient): JsonResponse
    {
        // On génère une clé de cache spécifique pour la ville.
        $cacheKey = "weather_" . $town;

        // On récupère les données météo depuis le cache ou on effectue la requête si non disponible.
        $cachedWeatherData = $this->cachePool->get($cacheKey, function (ItemInterface $item) use ($httpClient, $town) {
            // On définit l'expiration du cache.
            $item->expiresAfter(600); // Expire après 600 secondes (10 minutes).

            // On tag les éléments pour pouvoir les supprimer en groupe par la suite.
            $item->tag("weatherCache");
            // On effectue la requête vers l'API OpenWeather
            $apiResponse = $httpClient->request(
                'GET',
                "https://api.openweathermap.org/data/2.5/weather?q={$town}&appid=" . $this->apiKey
            );

            if ($apiResponse->getStatusCode() !== 200) {
                throw new \Exception('Weather data not found');
            }

            // On convertit le contenu de la réponse JSON renvoyée par l'API en un tableau associatif.
            $weatherInfo = json_decode($apiResponse->getContent(), true);

            // On vérifie si les données existent et on extrait les données.
            if (isset($weatherInfo['weather']) && !empty($weatherInfo['weather'])) {
                $description = $weatherInfo['weather'][0]['description'];
            } else {
                return new JsonResponse(['error' => 'Weather data not found'], 404);
            }

            // On structure la réponse JSON.
            return [
                'city' => $town,
                'weather' => $description,
            ];
        });

        return new JsonResponse($cachedWeatherData, 200);
    }
}
