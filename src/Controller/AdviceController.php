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
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

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
    #[OA\Response(response: 401, description: 'Le token JWT est manquant.')]
    #[OA\Response(response: 404, description: 'Mois non trouvé. Cela peut se produire dans les deux situations suivantes :
    1. L\'ID du mois spécifié n\'existe pas dans la base de données.
    2. Le mois existe, mais aucun conseil n\'est associé à ce mois.')]
    #[OA\Tag(name: 'Advices')]
    #[Security(name: 'Bearer')]

    public function getAdviceByMonth(
        $mois,
        MonthRepository $monthRepository,
        SerializerInterface $serializer
    ): JsonResponse {

        // On récupère le mois spécifié en base de données.
        $month = $monthRepository->find($mois);

        // On vérifie si le mois existe.
        if (!$month) {
            throw new NotFoundHttpException("Le mois avec l'ID $mois n'existe pas.");
        }

        // On récupère la liste des conseils associés au mois.
        $advices = $month->getAdviceList();

        // On vérifie si des conseils existent.
        if ($advices->isEmpty()) {
            throw new NotFoundHttpException("Aucun conseil trouvé pour le mois avec l'ID $mois.");
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
    #[OA\Response(response: 401, description: 'Le token JWT est manquant.')]
    #[OA\Response(response: 404, description: 'Aucun conseil trouvé pour le mois en cours.')]
    #[OA\Tag(name: 'Advices')]
    #[Security(name: 'Bearer')]

    public function getAdviceByCurrentMonth(
        MonthRepository $monthRepository,
        SerializerInterface $serializer
    ): JsonResponse {

        // On obtient le mois en cours.
        $currentMonth = (new \DateTime())->format('n');
        $month = $monthRepository->find($currentMonth);

        // On récupère la liste des conseils associés au mois actuel.
        $advices = $month->getAdviceList();

        // On vérifie si des conseils existent.
        if ($advices->isEmpty()) {
            throw new NotFoundHttpException("Aucun conseil trouvé pour le mois en cours.");
        }

        // On sérialise la liste des conseils pour la retourner dans la réponse.
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
    #[IsGranted('ROLE_ADMIN', message: 'Vous n\'avez pas les droits suffisants pour ajouter un nouveau conseil.')]
    #[Route('/api/conseil', name: 'app_createAdvice', methods: ['POST'])]
    #[OA\RequestBody(
        required: true,
        description: 'Données du nouveau conseil',
        content: new OA\JsonContent(
            type: 'object',
            properties: [
                new OA\Property(property: 'description', type: 'string', example: 'Conseil de jardinage'),
                new OA\Property(property: 'months', type: 'array', items: new OA\Items(type: 'integer'), example: [2])
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
    #[OA\Response(response: 401, description: 'Le token JWT est manquant.')]
    #[OA\Response(response: 400, description: 'Erreur de validation.')]
    #[OA\Response(response: 404, description: 'Au moins un moins associé au conseil est invalide.')]
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

        // On désérialise le contenu JSON en un objet Advice mais il n'inclut pas le ou les mois associés au conseil.
        $advice = $serializer->deserialize($jsonAdvice, Advice::class, 'json', ["groups" => "createAdvice"]);

        // On récupère le ou les mois associés au conseil depuis la requête.
        $content = $request->toArray();
        $months = $content['months'] ?? [];

        // On associe le ou les mois au conseil.
        foreach ($months as $monthNumber) {
            $month = $monthRepository->find($monthNumber);

            if ($month) {
                $advice->addMonth($month);
            } else {
                throw new NotFoundHttpException("Mois invalide : $monthNumber.");
            }
        }

        // On vérifie les contraintes de validation.
        $errors = $validator->validate($advice);
        if ($errors->count() > 0) {
            return new JsonResponse($serializer->serialize($errors, 'json'), JsonResponse::HTTP_BAD_REQUEST, [], true);
        }

        // On enregistre le nouveau conseil en base de données.
        $entityManager->persist($advice);
        $entityManager->flush();

        // On sérialise le conseil créé pour le retourner dans la réponse.
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
    #[IsGranted('ROLE_ADMIN', message: 'Vous n\'avez pas les droits suffisants pour modifier ce conseil.')]
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
    #[OA\Response(response: 200, description: 'Conseil modifié avec succès.')]
    #[OA\Response(response: 400, description: 'Aucune donnée à mettre à jour ou erreur de validation.')]
    #[OA\Response(response: 401, description: 'Le token JWT est manquant.')]
    #[OA\Response(response: 404, description: 'Le conseil à modifier n\'a pas été trouvé en base de données.')]
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

        // On vérifie que la requête ne soit pas vide.
        if (empty($request->getContent())) {
            throw new BadRequestHttpException('Aucune donnée à mettre à jour.');
        }

        // On récupère le conseil à mettre à jour en base de données.
        $currentAdvice = $adviceRepository->find($id);
        if (!$currentAdvice) {
            throw new NotFoundHttpException("Conseil avec l'ID $id non trouvé.");
        }

        // On désérialise les données de la requête dans l'objet existant en modifiant uniquement les propriétés envoyées.
        // À ce stade, les mois associés ne seront pas modifiés, même s'ils sont présents dans la requête. 
        $updatedAdvice = $serializer->deserialize($request->getContent(), Advice::class, 'json', [AbstractNormalizer::OBJECT_TO_POPULATE => $currentAdvice]);

        // On récupère le contenu de la requête.
        $content = $request->toArray();

        // Si la clé 'months' est présente dans la requête, on met à jour les mois associés.
        if (isset($content['months'])) {
            $months = $content['months'];

            // On supprime les mois existants.
            $updatedAdvice->getMonths()->clear();

            // On associe les nouveaux mois au conseil.
            foreach ($months as $monthNumber) {
                $month = $monthRepository->find($monthNumber);
                if ($month) {
                    $updatedAdvice->addMonth($month);
                    $entityManager->persist($month);
                } else {
                    throw new NotFoundHttpException("Mois invalide : $monthNumber.");
                }
            }
        }

        // On vérifie les contraintes de validation.
        $errors = $validator->validate($updatedAdvice);
        if ($errors->count() > 0) {
            return new JsonResponse($serializer->serialize($errors, 'json'), JsonResponse::HTTP_BAD_REQUEST, [], true);
        }

        // On met à jour le conseil en base de données.
        $entityManager->flush();

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }

    /**
     * Permet de supprimer un conseil existant.
     * 
     * @param int $id 
     * @param AdviceRepository $adviceRepository 
     * @param EntityManagerInterface $entityManager 
     * @return JsonResponse
     */
    #[IsGranted('ROLE_ADMIN', message: 'Vous n\'avez pas les droits suffisants pour supprimer ce conseil.')]
    #[Route('/api/conseil/{id}', name: 'app_deleteAdvice', methods: ['DELETE'])]
    #[OA\Parameter(
        name: 'id',
        in: 'path',
        description: 'ID du conseil à supprimer',
        required: true,
        schema: new OA\Schema(type: 'integer')
    )]
    #[OA\Response(response: 204, description: 'Conseil supprimé avec succès.')]
    #[OA\Response(response: 401, description: 'Le token JWT est manquant.')]
    #[OA\Response(response: 404, description: 'Conseil à supprimer non trouvé.')]
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
