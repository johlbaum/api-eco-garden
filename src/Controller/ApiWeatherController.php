<?php

namespace App\Controller;

use App\Entity\User;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\Cache\TagAwareCacheInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use OpenApi\Attributes as OA;

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
     * 
     * @param string $ville Le nom de la ville pour laquelle récupérer la météo.
     * @param HttpClientInterface $httpClient 
     * @return JsonResponse 
     */
    #[Route('/api/meteo/{ville}', name: 'app_getWeatherByTown', methods: ['GET'])]
    #[OA\Parameter(
        name: 'ville',
        in: 'path',
        description: 'Le nom de la ville pour laquelle récupérer la météo',
        required: true,
        example: 'Paris',
        schema: new OA\Schema(type: 'string')
    )]
    #[OA\Response(
        response: 200,
        description: 'Retourne les données météo de la ville spécifiée',
        content: new OA\JsonContent(
            type: 'object',
            properties: [
                new OA\Property(property: 'city', type: 'string', example: 'Paris'),
                new OA\Property(property: 'weather', type: 'string', example: 'Ensoleillé')
            ]
        )
    )]
    #[OA\Response(response: 401, description: 'Le token JWT est manquant. Vous devez vous authentifier.')]
    #[OA\Response(response: 404, description: 'Ville non trouvée.')]
    #[OA\Tag(name: 'Weather')]

    public function getWeatherByTown(string $ville, HttpClientInterface $httpClient): JsonResponse
    {
        return $this->fetchWeatherData($ville, $httpClient);
    }

    /**
     * Permet de retourner la météo de la ville de l'utilisateur authentifié.
     * 
     * @param HttpClientInterface $httpClient 
     * @return JsonResponse 
     */
    #[Route('/api/meteo', name: 'app_getWeatherByUserTown', methods: ['GET'])]
    #[OA\Response(
        response: 200,
        description: 'Retourne les données météo de la ville de l\'utilisateur',
        content: new OA\JsonContent(
            type: 'object',
            properties: [
                new OA\Property(property: 'city', type: 'string', example: 'Lyon'),
                new OA\Property(property: 'weather', type: 'string', example: 'Nuageux')
            ]
        )
    )]
    #[OA\Response(response: 401, description: 'Le token JWT est manquant. Vous devez vous authentifier.')]
    #[OA\Response(response: 404, description: 'Ville non trouvée')]
    #[OA\Tag(name: 'Weather')]

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
     * 
     * @param string $town Le nom de la ville pour laquelle récupérer les données météo.
     * @param HttpClientInterface $httpClient 
     * @return JsonResponse 
     */
    private function fetchWeatherData(string $town, HttpClientInterface $httpClient): JsonResponse
    {
        // On génère une clé de cache spécifique pour la ville.
        $cacheKey = "weather_" . $town;

        // On vérifie si les données météo sont en cache. Si non, on effectue une requête et on les met en cache.
        $weatherData = $this->cachePool->get($cacheKey, function (ItemInterface $item) use ($httpClient, $town) {

            // On définit l'expiration du cache.
            $item->expiresAfter(600); // Expire après 600 secondes (10 minutes).

            // On effectue la requête vers l'API OpenWeather
            $apiResponse = $httpClient->request(
                'GET',
                "https://api.openweathermap.org/data/2.5/weather?q={$town}&appid=" . $this->apiKey . "&lang=fr"
            );

            if ($apiResponse->getStatusCode() !== 200) {
                throw new \Exception('Données météo introuvables.');
            }

            // On convertit le contenu de la réponse JSON renvoyée par l'API en un tableau associatif.
            $weatherInfo = json_decode($apiResponse->getContent(), true);

            // On vérifie si les données existent et on extrait les données.
            if (isset($weatherInfo['weather']) && !empty($weatherInfo['weather'])) {
                $description = $weatherInfo['weather'][0]['description'];
            } else {
                return new JsonResponse(['error' => 'Données météo introuvables.'], 404);
            }

            // On structure la réponse JSON pour la mettre en cache.
            return [
                'city' => $town,
                'weather' => $description,
            ];
        });

        // On retourne les données (du cache ou de la requête) au client.
        return new JsonResponse($weatherData, 200);
    }
}
