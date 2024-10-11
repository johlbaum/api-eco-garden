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
     * Permet de récupérer tous les conseils du mois spécifié.
     */
    #[Route('/api/conseil/{mois}', name: 'app_advice_by_month', methods: ['GET'])]
    public function getAdviceByMonth($mois, MonthRepository $monthRepository, SerializerInterface $serializer): JsonResponse
    {
        // On récupère le mois spécifié en base de données.
        $month = $monthRepository->find($mois);

        // On vérifie si le mois existe.
        if (!$month) {
            throw new NotFoundHttpException("Mois avec l'ID $mois non trouvé.");
        }

        // On récupère la liste des conseils associés au mois.
        $advices = $month->getAdviceList();

        // On vérifie si des conseils existent.
        if ($advices->isEmpty()) {
            return new JsonResponse(['error' => 'Aucun conseil trouvé.'], Response::HTTP_NOT_FOUND);
        }

        // On sérialise la liste des conseils pour la retourner dans la réponse.
        $jsonAdvices = $serializer->serialize($advices, 'json', ['groups' => 'getAdvice']);

        return new JsonResponse($jsonAdvices, Response::HTTP_OK, [], true);
    }

    /**
     * Permet de récupérer tous les conseils du mois en cours.
     */
    #[Route('/api/conseil/', name: 'app_get_advice_by_current_month', methods: 'GET')]
    public function getAdviceByCurrentMonth(MonthRepository $monthRepository, SerializerInterface $serializer): JsonResponse
    {
        // On obtient le mois en cours.
        $currentMonth = (new \DateTime())->format('n');
        $month = $monthRepository->find($currentMonth);

        // On vérifie si le mois existe.
        if (!$month) {
            throw new NotFoundHttpException("Mois actuel ($currentMonth) non trouvé.");
        }

        // On récupère la liste des conseils associés au mois actuel.
        $advices = $month->getAdviceList();

        // On vérifie si des conseils existent.
        if ($advices->isEmpty()) {
            return new JsonResponse(['error' => 'Aucun conseil trouvé.'], Response::HTTP_NOT_FOUND);
        }

        // On sérialise la liste des conseils pour la retourner la réponse.
        $jsonAdvices = $serializer->serialize($advices, 'json', ['groups' => 'getAdvice']);

        return new JsonResponse($jsonAdvices, Response::HTTP_OK, [], true);
    }

    /**
     * Permet d’ajouter un nouveau conseil. 
     */
    #[Route('/api/conseil', name: 'app_createAdvice', methods: ['POST'])]
    public function createAdvice(
        Request $request,
        SerializerInterface $serializer,
        EntityManagerInterface $entityManager,
        MonthRepository $monthRepository,
        ValidatorInterface $validator
    ): JsonResponse {
        // On récupère le contenu de la requête.
        $jsonAdvice = $request->getContent();

        // On désérialise le contenu JSON en un objet Advice.
        $advice = $serializer->deserialize($jsonAdvice, Advice::class, 'json');

        // On récupère les mois associés au conseil depuis la requête.
        $content = $request->toArray();
        $months = $content['month'] ?? [];

        // On associe les mois au conseil.
        foreach ($months as $monthNumber) {
            $month = $monthRepository->find($monthNumber);

            if ($month) {
                $advice->addMonth($month);
            } else {
                throw new NotFoundHttpException("Mois invalide : $monthNumber.");
            }
        }

        // On vérifie les erreurs de validation.
        $validationErrors = $validator->validate($advice);
        if ($validationErrors->count() > 0) {
            return new JsonResponse($serializer->serialize($validationErrors, 'json'), JsonResponse::HTTP_BAD_REQUEST, [], true);
        }

        // On enregistre le nouveau conseil en base de données.
        $entityManager->persist($advice);
        $entityManager->flush();

        // On sérialise l'objet conseil créé pour le retourner dans la réponse.
        $createdAdviceJson = $serializer->serialize($advice, 'json', ['groups' => 'getAdvice']);

        return new JsonResponse($createdAdviceJson, Response::HTTP_CREATED, [], true);
    }

    /**
     * Permet de mettre à jour un conseil. 
     */
    #[Route('/api/conseil/{id}', name: 'app_updateAdvice', methods: ['PUT'])]
    public function updateAdvice(
        int $id,
        Request $request,
        SerializerInterface $serializer,
        AdviceRepository $adviceRepository,
        EntityManagerInterface $entityManager,
        MonthRepository $monthRepository
    ): JsonResponse {
        // On récupère l'entité existante par son ID.
        $currentAdvice = $adviceRepository->find($id);
        if (!$currentAdvice) {
            throw new NotFoundHttpException("Conseil avec l'ID $id non trouvé.");
        }

        // On désérialise des nouvelles données dans l'entité existante.
        $updatedAdvice = $serializer->deserialize(
            $request->getContent(),
            Advice::class,
            'json',
            [AbstractNormalizer::OBJECT_TO_POPULATE => $currentAdvice]
        );

        // On met à jour des mois s'ils sont présents dans la requête.
        $content = $request->toArray();
        $months = $content['month'] ?? [];

        if (!empty($months)) {
            $currentAdvice->clearMonths();  // On réinitialise des mois actuels.
            foreach ($months as $monthNumber) {
                $month = $monthRepository->find($monthNumber);
                if ($month) {
                    $currentAdvice->addMonth($month);
                } else {
                    throw new NotFoundHttpException("Mois invalide : $monthNumber.");
                }
            }
        }

        // On enregistre le conseil mis à jour en base de données.
        $entityManager->persist($updatedAdvice);
        $entityManager->flush();

        return new JsonResponse(null, JsonResponse::HTTP_NO_CONTENT);
    }

    /**
     * Permet de supprimer un conseil. 
     */
    #[Route('/api/conseil/{id}', name: 'app_deleteAdvice', methods: ['DELETE'])]
    public function deleteAdvice(int $id, EntityManagerInterface $entityManager, AdviceRepository $adviceRepository): JsonResponse
    {
        // On récupère le conseil à supprimer en base de données.
        $currentAdvice = $adviceRepository->find($id);

        // On vérifie si le conseil existe.
        if (!$currentAdvice) {
            throw new NotFoundHttpException("Conseil avec l'ID $id non trouvé.");
        }

        // On supprime le conseil en base de données.
        $entityManager->remove($currentAdvice);
        $entityManager->flush();

        return new JsonResponse(null, JsonResponse::HTTP_NO_CONTENT);
    }
}
