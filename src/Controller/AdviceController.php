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
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Nelmio\ApiDocBundle\Annotation\Security;
use OpenApi\Attributes as OA;

class AdviceController extends AbstractController
{
    /**
     * Permet de récupérer tous les conseils du mois spécifié.
     * 
     * @param int $mois L'ID du mois pour lequel récupérer les conseils.
     * @param MonthRepository $monthRepository 
     * @param SerializerInterface $serializer 
     * @return JsonResponse
     */
    #[Route('/api/conseil/{mois}', name: 'app_advice_by_month', methods: ['GET'])]
    #[OA\Parameter(
        name: 'mois',
        in: 'path',
        description: 'L\'ID du mois pour lequel récupérer les conseils',
        required: true,
        example: 2,
        schema: new OA\Schema(type: 'integer')
    )]
    #[OA\Response(
        response: 200,
        description: 'Retourne tous les conseils du mois spécifié',
        content: new OA\JsonContent(
            type: 'array',
            items: new OA\Items(
                type: 'object',
                properties: [
                    new OA\Property(property: 'id', type: 'integer', example: 1),
                    new OA\Property(property: 'description', type: 'string', example: 'Un conseil utile.'),
                    new OA\Property(
                        property: 'months',
                        type: 'array',
                        items: new OA\Items(
                            type: 'object',
                            properties: [
                                new OA\Property(property: 'id', type: 'integer', example: 2),
                                new OA\Property(property: 'monthNumber', type: 'integer', example: 2)
                            ]
                        )
                    )
                ]
            )
        )
    )]
    #[OA\Response(response: 401, description: 'Le token JWT est manquant. Vous devez vous authentifier.')]
    #[OA\Response(response: 404, description: 'Mois non trouvé')]
    #[OA\Response(response: 204, description: 'Aucun conseil trouvé pour ce mois')]
    #[OA\Tag(name: 'Advices')]
    #[Security(name: 'Bearer')]

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
     * 
     * @param MonthRepository $monthRepository
     * @param SerializerInterface $serializer 
     * @return JsonResponse
     */
    #[Route('/api/conseil/', name: 'app_get_advice_by_current_month', methods: 'GET')]
    #[OA\Response(
        response: 200,
        description: 'Retourne tous les conseils du mois en cours',
        content: new OA\JsonContent(
            type: 'array',
            items: new OA\Items(
                type: 'object',
                properties: [
                    new OA\Property(property: 'id', type: 'integer', example: 3),
                    new OA\Property(property: 'description', type: 'string', example: 'Conseil utile pour le mois.'),
                    new OA\Property(
                        property: 'months',
                        type: 'array',
                        items: new OA\Items(
                            type: 'object',
                            properties: [
                                new OA\Property(property: 'id', type: 'integer', example: 5),
                                new OA\Property(property: 'monthNumber', type: 'integer', example: 5)
                            ]
                        )
                    )
                ]
            )
        )
    )]
    #[OA\Response(response: 401, description: 'Le token JWT est manquant. Vous devez vous authentifier.')]
    #[OA\Response(response: 404, description: 'Mois actuel non trouvé')]
    #[OA\Response(response: 204, description: 'Aucun conseil trouvé pour le mois actuel')]
    #[OA\Tag(name: 'Advices')]
    #[Security(name: 'Bearer')]

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
     * 
     * @param Request $request 
     * @param SerializerInterface $serializer 
     * @param EntityManagerInterface $entityManager 
     * @param MonthRepository $monthRepository 
     * @param ValidatorInterface $validator
     * @return JsonResponse
     */
    #[IsGranted('ROLE_ADMIN', message: 'Vous n\'avez pas les droits suffisants pour ajouter un nouveau conseil')]
    #[Route('/api/conseil', name: 'app_createAdvice', methods: ['POST'])]
    #[OA\RequestBody(
        required: true,
        description: 'Données du nouveau conseil',
        content: new OA\JsonContent(
            type: 'object',
            properties: [
                new OA\Property(property: 'description', type: 'string', example: 'Conseil de jardinage'),
                new OA\Property(property: 'month', type: 'array', items: new OA\Items(type: 'integer'), example: [2])
            ]
        )
    )]
    #[OA\Response(
        response: 201,
        description: 'Nouveau conseil créé avec succès',
        content: new OA\JsonContent(
            type: 'object',
            properties: [
                new OA\Property(property: 'id', type: 'integer', example: 1),
                new OA\Property(property: 'description', type: 'string', example: 'Conseil de jardinage'),
                new OA\Property(
                    property: 'months',
                    type: 'array',
                    items: new OA\Items(
                        type: 'object',
                        properties: [
                            new OA\Property(property: 'id', type: 'integer', example: 2),
                            new OA\Property(property: 'monthNumber', type: 'integer', example: 2)
                        ]
                    )
                )
            ]
        )
    )]
    #[OA\Response(response: 401, description: 'Le token JWT est manquant. Vous devez vous authentifier.')]
    #[OA\Response(response: 400, description: 'Erreur de validation')]
    #[OA\Response(response: 404, description: 'Mois invalide')]
    #[OA\Tag(name: 'Advices')]
    #[Security(name: 'Bearer')]

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
            return new JsonResponse($validationErrors, Response::HTTP_BAD_REQUEST);
        }

        // On persiste le nouveau conseil en base de données.
        $entityManager->persist($advice);
        $entityManager->flush();

        // On sérialise le nouveau conseil pour la réponse.
        $jsonResponse = $serializer->serialize($advice, 'json', ['groups' => 'getAdvice']);

        return new JsonResponse($jsonResponse, Response::HTTP_CREATED, [], true);
    }

    /**
     * Permet de modifier un conseil existant.
     * 
     * @param Request $request 
     * @param int $id 
     * @param AdviceRepository $adviceRepository 
     * @param EntityManagerInterface $entityManager 
     * @param SerializerInterface $serializer 
     * @param MonthRepository $monthRepository 
     * @param ValidatorInterface $validator
     * @return JsonResponse
     */
    #[IsGranted('ROLE_ADMIN', message: 'Vous n\'avez pas les droits suffisants pour modifier ce conseil')]
    #[Route('/api/conseil/{id}', name: 'app_updateAdvice', methods: ['PUT'])]
    #[OA\Parameter(
        name: 'id',
        in: 'path',
        description: 'ID du conseil à modifier',
        required: true,
        schema: new OA\Schema(type: 'integer')
    )]
    #[OA\RequestBody(
        required: true,
        description: 'Données à mettre à jour',
        content: new OA\JsonContent(
            type: 'object',
            properties: [
                new OA\Property(property: 'description', type: 'string', example: 'Nouveau conseil de jardinage'),
                new OA\Property(property: 'month', type: 'array', items: new OA\Items(type: 'integer'), example: [3])
            ]
        )
    )]
    #[OA\Response(response: 200, description: 'Conseil modifié avec succès')]
    #[OA\Response(response: 400, description: 'Erreur de validation')]
    #[OA\Response(response: 401, description: 'Le token JWT est manquant. Vous devez vous authentifier.')]
    #[OA\Response(response: 404, description: 'Conseil non trouvé')]
    #[OA\Tag(name: 'Advices')]
    #[Security(name: 'Bearer')]

    public function updateAdvice(
        Request $request,
        int $id,
        AdviceRepository $adviceRepository,
        EntityManagerInterface $entityManager,
        SerializerInterface $serializer,
        MonthRepository $monthRepository,
        ValidatorInterface $validator
    ): JsonResponse {
        // On récupère le conseil existant.
        $advice = $adviceRepository->find($id);
        if (!$advice) {
            throw new NotFoundHttpException("Conseil avec l'ID $id non trouvé.");
        }

        // On récupère les données à mettre à jour.
        $jsonAdvice = $request->getContent();
        $advice = $serializer->deserialize($jsonAdvice, Advice::class, 'json', [AbstractNormalizer::OBJECT_TO_POPULATE => $advice]);

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
            return new JsonResponse($validationErrors, Response::HTTP_BAD_REQUEST);
        }

        // On met à jour le conseil en base de données.
        $entityManager->flush();

        // On sérialise le conseil mis à jour pour la réponse.
        $jsonResponse = $serializer->serialize($advice, 'json', ['groups' => 'getAdvice']);

        return new JsonResponse($jsonResponse, Response::HTTP_OK, [], true);
    }

    /**
     * Permet de supprimer un conseil existant.
     * 
     * @param int $id 
     * @param AdviceRepository $adviceRepository 
     * @param EntityManagerInterface $entityManager 
     * @return JsonResponse
     */
    #[IsGranted('ROLE_ADMIN', message: 'Vous n\'avez pas les droits suffisants pour supprimer ce conseil')]
    #[Route('/api/conseil/{id}', name: 'app_deleteAdvice', methods: ['DELETE'])]
    #[OA\Parameter(
        name: 'id',
        in: 'path',
        description: 'ID du conseil à supprimer',
        required: true,
        schema: new OA\Schema(type: 'integer')
    )]
    #[OA\Response(response: 204, description: 'Conseil supprimé avec succès')]
    #[OA\Response(response: 401, description: 'Le token JWT est manquant. Vous devez vous authentifier.')]
    #[OA\Response(response: 404, description: 'Conseil non trouvé')]
    #[OA\Tag(name: 'Advices')]
    #[Security(name: 'Bearer')]

    public function deleteAdvice(
        int $id,
        AdviceRepository $adviceRepository,
        EntityManagerInterface $entityManager
    ): JsonResponse {
        // On récupère le conseil à supprimer.
        $advice = $adviceRepository->find($id);
        if (!$advice) {
            throw new NotFoundHttpException("Conseil avec l'ID $id non trouvé.");
        }

        // On supprime le conseil en base de données.
        $entityManager->remove($advice);
        $entityManager->flush();

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }
}
