<?php

namespace App\Controller;

use App\Entity\Advice;
use App\Repository\AdviceRepository;
use App\Repository\MonthRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;
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

    /**
     * Permet d’ajouter un conseil. 
     */
    #[Route('api/conseil', name: 'app_createAdvice', methods: ['POST'])]
    public function createAdvice(Request $request, SerializerInterface $serializer, EntityManagerInterface $entityManager, MonthRepository $monthRepository): JsonResponse
    {
        $jsonAdvice = $request->getContent();

        $advice = $serializer->deserialize($jsonAdvice, Advice::class, 'json');

        $content = $request->toArray();

        $months = $content['month'] ?? [];

        foreach ($months as $monthNumber) {
            $month = $monthRepository->find($monthNumber);

            if ($month) {
                $advice->addMonth($month);
            } else {
                return new JsonResponse(['error' => 'Invalid month: ' . $monthNumber], Response::HTTP_NOT_FOUND);
            }
        }

        $entityManager->persist($advice);
        $entityManager->flush();

        $advice = $serializer->serialize($advice, 'json', ['groups' => 'getAdvice']);

        return new JsonResponse($advice, Response::HTTP_CREATED, [], true);
    }

    /**
     * Permet de mettre à jour un conseil. 
     */
    #[Route('/api/conseil/{id}', name: 'app_updateAdvice', methods: ['PUT'])]
    public function updateAdvice($id, AdviceRepository $adviceRepository, Request $request, SerializerInterface $serializer, EntityManagerInterface $entityManager, MonthRepository $monthRepository): JsonResponse
    {
        $currentAdvice = $adviceRepository->find($id);
        $jsonAdvice = $request->getContent();

        $advice = $serializer->deserialize($jsonAdvice, Advice::class, 'json', [AbstractNormalizer::OBJECT_TO_POPULATE => $currentAdvice]);

        $content = $request->toArray();
        $months = $content['month'] ?? [];

        foreach ($months as $monthNumber) {
            $month = $monthRepository->find($monthNumber);

            if ($month) {
                $advice->addMonth($month);
            } else {
                return new JsonResponse(['error' => 'Invalid month: ' . $monthNumber], Response::HTTP_NOT_FOUND);
            }
        }

        $entityManager->persist($advice);
        $entityManager->flush();

        return new JsonResponse(null, JsonResponse::HTTP_NO_CONTENT);
    }
}
