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
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class AdviceController extends AbstractController
{
    /**
     * Permet de récupérer un tableau avec tous les conseils du mois spécifié
     */
    #[Route('/api/conseil/{mois}', name: 'app_advice_by_month', methods: ['GET'])]
    public function getAdviceByMonce($mois, MonthRepository $monthRepository, SerializerInterface $serializer): JsonResponse
    {
        $month = $monthRepository->find($mois);

        if (!$month) {
            throw new NotFoundHttpException("Month with ID $mois not found.");
        }

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

        if (!$month) {
            throw new NotFoundHttpException("Current month ($currentMonth) not found.");
        }

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
    public function createAdvice(
        Request $request,
        SerializerInterface $serializer,
        EntityManagerInterface $entityManager,
        MonthRepository $monthRepository,
        ValidatorInterface $validator
    ): JsonResponse {
        $jsonAdvice = $request->getContent();

        $advice = $serializer->deserialize($jsonAdvice, Advice::class, 'json');

        $content = $request->toArray();
        $months = $content['month'] ?? [];

        foreach ($months as $monthNumber) {
            $month = $monthRepository->find($monthNumber);

            if ($month) {
                $advice->addMonth($month);
            } else {
                throw new NotFoundHttpException("Invalid month: $monthNumber.");
            }
        }

        // On vérifie les erreurs
        $errors = $validator->validate($advice);
        if ($errors->count() > 0) {
            return new JsonResponse($serializer->serialize($errors, 'json'), JsonResponse::HTTP_BAD_REQUEST, [], true);
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
    public function updateAdvice(
        $id,
        Request $request,
        SerializerInterface $serializer,
        AdviceRepository $adviceRepository,
        EntityManagerInterface $entityManager,
        MonthRepository $monthRepository,
        ValidatorInterface $validator
    ): JsonResponse {
        // On récupère l'entité existante par son ID.
        $currentAdvice = $adviceRepository->find($id);
        if (!$currentAdvice) {
            throw new NotFoundHttpException("Advice with ID $id not found.");
        }

        // Désérialisation des nouvelles données dans l'entité existante.
        $updatedAdvice = $serializer->deserialize($request->getContent(), Advice::class, 'json', [AbstractNormalizer::OBJECT_TO_POPULATE => $currentAdvice]);

        // Mise à jour des mois s'ils sont présents dans la requête.
        $content = $request->toArray();
        $months = $content['month'] ?? [];

        if (!empty($months)) {
            $currentAdvice->clearMonths();  // Réinitialisation des mois actuels.
            foreach ($months as $monthNumber) {
                $month = $monthRepository->find($monthNumber);
                if ($month) {
                    $currentAdvice->addMonth($month);
                } else {
                    throw new NotFoundHttpException("Invalid month: $monthNumber.");
                }
            }
        }

        // Sauvegarde des changements.
        $entityManager->persist($updatedAdvice);
        $entityManager->flush();

        return new JsonResponse(null, JsonResponse::HTTP_NO_CONTENT);
    }

    /**
     * Permet de supprimer un conseil. 
     */
    #[Route('/api/conseil/{id}', name: 'app_deleteAdvice', methods: ['DELETE'])]
    public function deleteAdvice($id, EntityManagerInterface $entityManager, AdviceRepository $adviceRepository): JsonResponse
    {
        $currentAdvice = $adviceRepository->find($id);

        if (!$currentAdvice) {
            throw new NotFoundHttpException("Advice with ID $id not found.");
        }

        $entityManager->remove($currentAdvice);
        $entityManager->flush();

        return new JsonResponse(null, JsonResponse::HTTP_NO_CONTENT);
    }
}
