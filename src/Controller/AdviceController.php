<?php

namespace App\Controller;

use App\Repository\MonthRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Serializer\SerializerInterface;

class AdviceController extends AbstractController
{
    /**
     * Permet de récupérer un tableau avec tous les conseils du mois spécifié
     */
    #[Route('/api/conseil/{mois}', name: 'app_advice_by_month', methods: ['GET'])]
    public function getAdviceByMonce($mois, MonthRepository $monthRepository, SerializerInterface $serializer): JsonResponse
    {
        $month = $monthRepository->find($mois);

        $advices = $month->getAdviceList();

        if ($advices->isEmpty()) {
            return new JsonResponse(['error' => 'Advices not found.'], Response::HTTP_NOT_FOUND);
        }

        $jsonAdvices = $serializer->serialize($advices, 'json', ['groups' => 'getAdvice']);

        return new JsonResponse($jsonAdvices, Response::HTTP_OK, [], true);
    }

    /**
     * Permet de récupérer un tableau avec tous les conseils du mois en cours.
     */
    #[Route('/api/conseil/', name: 'app_get_advice_by_current_month', methods: 'GET')]
    public function getAdviceByCurrentMonth(MonthRepository $monthRepository, SerializerInterface $serializer): JsonResponse
    {
        $currentMonth = (new \DateTime())->format('n');
        $month = $monthRepository->find($currentMonth);

        $advices = $month->getAdviceList();

        if ($advices->isEmpty()) {
            return new JsonResponse(['error' => 'Advices not found.'], Response::HTTP_NOT_FOUND);
        }

        $jsonAdvices = $serializer->serialize($advices, 'json', ['groups' => 'getAdvice']);

        return new JsonResponse($jsonAdvices, Response::HTTP_OK, [], true);
    }
}
