<?php

namespace App\Controller;

use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use OpenApi\Attributes as OA;

class UserController extends AbstractController
{
    /**
     * Permet de créer un nouveau compte utilisateur.
     * 
     * @param Request $request
     * @param SerializerInterface $serializer
     * @param ValidatorInterface $validator
     * @param UserPasswordHasherInterface $userPasswordHasher
     * @param EntityManagerInterface $entityManager
     * @return JsonResponse
     */
    #[Route('/api/user', name: 'app_createUser', methods: ['POST'])]
    #[OA\RequestBody(
        required: true,
        description: 'Données pour la création d\'un nouvel utilisateur',
        content: new OA\JsonContent(
            type: 'object',
            properties: [
                new OA\Property(property: 'email', type: 'string', example: 'admin.admin@hotmail.fr'),
                new OA\Property(property: 'password', type: 'string', example: 'password'),
                new OA\Property(property: 'town', type: 'string', example: 'Lyon')
            ],
            required: ['email', 'password', 'town']
        )
    )]
    #[OA\Response(
        response: 201,
        description: 'Utilisateur créé avec succès',
        content: new OA\JsonContent(
            type: 'object',
            properties: [
                new OA\Property(property: 'id', type: 'integer', example: 1),
                new OA\Property(property: 'email', type: 'string', example: 'admin.admin@hotmail.fr'),
                new OA\Property(property: 'town', type: 'string', example: 'Lyon'),
                new OA\Property(
                    property: 'roles',
                    type: 'array',
                    items: new OA\Items(type: 'string', example: 'ROLE_USER')
                )
            ]
        )
    )]
    #[OA\Response(response: 400, description: 'Erreur de validation')]
    #[OA\Response(response: 500, description: 'Erreur : email dupliqué)')]
    #[OA\Tag(name: 'Users')]

    public function createUser(
        Request $request,
        SerializerInterface $serializer,
        ValidatorInterface $validator,
        UserPasswordHasherInterface $userPasswordHasher,
        EntityManagerInterface $entityManager
    ): JsonResponse {
        // On récupère le contenu de la requête.
        $requestContent = $request->getContent();

        // On désérialise le contenu JSON en un objet User.
        $newUser = $serializer->deserialize($requestContent, User::class, 'json');

        // On valide l'objet User et on retourne les erreurs s'il y en a.
        $validationErrors = $validator->validate($newUser);
        if ($validationErrors->count() > 0) {
            return new JsonResponse(
                $serializer->serialize($validationErrors, 'json'),
                JsonResponse::HTTP_BAD_REQUEST,
                [],
                true
            );
        }

        // On hash le mot de passe de l'utilisateur.
        $hashedPassword = $userPasswordHasher->hashPassword($newUser, $newUser->getPassword());
        $newUser->setPassword($hashedPassword);

        // On attribue un rôle User par défaut.
        $newUser->setRoles(['ROLE_USER']);

        // On enregistre le nouvel utilisateur dans la base de données.
        $entityManager->persist($newUser);
        $entityManager->flush();

        // On sérialise l'objet utilisateur créé pour le retourner dans la réponse.
        $createdUserJson = $serializer->serialize($newUser, 'json', ['groups' => 'createUser']);

        return new JsonResponse($createdUserJson, Response::HTTP_CREATED, [], true);
    }

    /**
     * Permet de mettre à jour un compte utilisateur.
     * 
     * @param int $id L'ID de l'utilisateur à mettre à jour
     * @param UserRepository $userRepository
     * @param Request $request
     * @param SerializerInterface $serializer
     * @param ValidatorInterface $validator
     * @param EntityManagerInterface $entityManager
     * @return JsonResponse
     */
    #[IsGranted('ROLE_ADMIN', message: 'Vous n\'avez pas les droits suffisants pour mettre à jour un compte')]
    #[Route('/api/user/{id}', name: 'app_updateUser', methods: ['PUT'])]
    #[OA\Parameter(
        name: 'id',
        in: 'path',
        required: true,
        description: 'ID de l\'utilisateur à mettre à jour',
        example: 1,
        schema: new OA\Schema(type: 'integer')
    )]
    #[OA\RequestBody(
        required: true,
        description: 'Données pour la mise à jour de l\'utilisateur',
        content: new OA\JsonContent(
            type: 'object',
            properties: [
                new OA\Property(property: 'email', type: 'string', example: 'admin.admin@hotmail.fr'),
                new OA\Property(property: 'password', type: 'string', example: 'newpassword'),
                new OA\Property(property: 'town', type: 'string', example: 'Lyon')
            ],
            required: ['email', 'town']
        )
    )]
    #[OA\Response(
        response: 200,
        description: 'Utilisateur mis à jour avec succès',
        content: new OA\JsonContent(
            type: 'object',
            properties: [
                new OA\Property(property: 'id', type: 'integer', example: 1),
                new OA\Property(property: 'email', type: 'string', example: 'admin.admin@hotmail.fr'),
                new OA\Property(property: 'town', type: 'string', example: 'Lyon'),
                new OA\Property(
                    property: 'roles',
                    type: 'array',
                    items: new OA\Items(type: 'string', example: 'ROLE_USER')
                )
            ]
        )
    )]
    #[OA\Response(response: 401, description: 'Le token JWT est manquant. Vous devez vous authentifier.')]
    #[OA\Response(response: 404, description: 'Utilisateur non trouvé')]
    #[OA\Response(response: 400, description: 'Erreur de validation')]
    #[OA\Response(response: 500, description: 'Erreur : email dupliqué)')]
    #[OA\Tag(name: 'Users')]
    public function updateUser(
        int $id,
        UserRepository $userRepository,
        Request $request,
        SerializerInterface $serializer,
        ValidatorInterface $validator,
        EntityManagerInterface $entityManager
    ): JsonResponse {
        // On récupère l'utilisateur à mettre à jour en base de données.
        $user = $userRepository->find($id);

        // On vérifie si l'utilisateur existe.
        if (!$user) {
            return new JsonResponse(['message' => 'User not found'], JsonResponse::HTTP_NOT_FOUND);
        }

        // On récupère le contenu de la requête.
        $jsonUser = $request->getContent();

        // On désérialise le contenu JSON en un objet User.
        $updatedUser = $serializer->deserialize(
            $jsonUser,
            User::class,
            'json',
            [AbstractNormalizer::OBJECT_TO_POPULATE => $user]
        );

        // On valide l'objet User et on retourne les erreurs s'il y en a.
        $validationErrors = $validator->validate($updatedUser);
        if ($validationErrors->count() > 0) {
            return new JsonResponse(
                $serializer->serialize($validationErrors, 'json'),
                JsonResponse::HTTP_BAD_REQUEST,
                [],
                true
            );
        }

        // On enregistre l'utilisateur en base de données.
        $entityManager->persist($updatedUser);
        $entityManager->flush();

        // On sérialise l'objet utilisateur mis à jour pour le retourner dans la réponse.
        $updatedUserJson = $serializer->serialize($updatedUser, 'json');

        return new JsonResponse($updatedUserJson, JsonResponse::HTTP_OK, [], true);
    }

    /**
     * Permet de supprimer un utilisateur.
     * 
     * @param int $id L'ID de l'utilisateur à supprimer
     * @param UserRepository $userRepository
     * @param EntityManagerInterface $entityManager
     * @return JsonResponse
     */
    #[IsGranted('ROLE_ADMIN', message: 'Vous n\'avez pas les droits suffisants pour supprimer un compte')]
    #[Route('/api/user/{id}', name: 'app_deleteUser', methods: ['DELETE'])]
    #[OA\Parameter(
        name: 'id',
        in: 'path',
        required: true,
        description: 'ID de l\'utilisateur à supprimer',
        example: 1,
        schema: new OA\Schema(type: 'integer')
    )]
    #[OA\Response(response: 204, description: 'Utilisateur supprimé avec succès')]
    #[OA\Response(response: 401, description: 'Le token JWT est manquant. Vous devez vous authentifier.')]
    #[OA\Response(response: 404, description: 'Utilisateur non trouvé')]
    #[OA\Tag(name: 'Users')]
    public function deleteUser(int $id, UserRepository $userRepository, EntityManagerInterface $entityManager): JsonResponse
    {
        // On récupère l'utilisateur à supprimer en base de données.
        $user = $userRepository->find($id);

        // On vérifie si l'utilisateur existe.
        if (!$user) {
            return new JsonResponse(['message' => 'User not found'], JsonResponse::HTTP_NOT_FOUND);
        }

        // On supprime l'utilisateur en base de données.
        $entityManager->remove($user);
        $entityManager->flush();

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }
}
